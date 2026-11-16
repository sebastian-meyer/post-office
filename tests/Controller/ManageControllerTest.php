<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Repository\AccountRepository;
use App\Tests\DatabaseTrait;
use App\Tests\TestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ManageControllerTest extends WebTestCase
{
    use DatabaseTrait;

    /**
     * Forms use stateless CSRF protection, which accepts requests from the same origin.
     */
    private const array SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    private KernelBrowser $client;

    private TestData $data;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->data = (new TestData())->persist($this->startDatabase());
    }

    public function testRegularUserHasNoManagementArea(): void
    {
        $this->client->loginUser($this->data->dave);
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('a[href="/manage"]');

        $this->client->request('GET', '/manage');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDomainManagerSeesOwnDomainOnly(): void
    {
        $this->get($this->data->alice, '/');
        self::assertSelectorExists('a.card[href="/manage"]');

        $crawler = $this->get($this->data->alice, '/manage');
        self::assertSame(['example.com'], $this->column($crawler, 0));

        $this->client->request('GET', '/manage/domain/example.org/accounts');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminSeesAllDomains(): void
    {
        self::assertSame(['example.com', 'example.org'], $this->column($this->get($this->data->admin, '/manage'), 0));
        $this->client->request('GET', '/manage/domain/example.org/accounts');
        self::assertResponseIsSuccessful();
    }

    public function testListManagerSeesTheirLists(): void
    {
        $crawler = $this->get($this->data->bob, '/manage');
        self::assertStringContainsString('team@example.com', $crawler->filter('table')->text());

        $this->client->request('GET', '/manage/domain/example.com/accounts');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDomainPageListsAccounts(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com/accounts');
        $addresses = array_map('trim', $this->column($crawler, 0));

        self::assertContains('alice@example.com', $addresses);
        self::assertContains('info@example.com', $addresses);
        self::assertContains('team@example.com', $addresses);
        self::assertNotContains('carol@example.org', $addresses);
    }

    public function testCreateMailbox(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com/new/mailbox');
        self::assertSelectorNotExists('select[name="mailbox[domain]"]');

        $form = $crawler->selectButton('Save')->form([
            'mailbox[username]' => 'erin',
            'mailbox[label]' => 'Erin',
            'mailbox[quota]' => '250',
            'mailbox[plainPassword]' => 'correct horse battery staple'
        ]);
        $this->client->submit($form, [], self::SAME_ORIGIN);

        self::assertResponseRedirects('/manage/domain/example.com/accounts', 303);
        $erin = $this->reload('erin@example.com');
        self::assertTrue($erin->isMailbox());
        self::assertSame(250, $erin->quota);
    }

    public function testCreateMailboxShowsValidationErrors(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com/new/mailbox');
        $form = $crawler->selectButton('Save')->form([
            'mailbox[username]' => 'bob',
            'mailbox[quota]' => '100',
            'mailbox[plainPassword]' => 'correct horse battery staple'
        ]);
        $this->client->submit($form, [], self::SAME_ORIGIN);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback');
    }

    public function testCreateCatchAllAlias(): void
    {
        $crawler = $this->get($this->data->carol, '/manage/domain/example.org/new/alias');
        $form = $crawler->selectButton('Save')->form([
            'alias[username]' => '',
            'alias[destination]' => 'carol@example.org'
        ]);
        $this->client->submit($form, [], self::SAME_ORIGIN);

        self::assertResponseRedirects('/manage/domain/example.org/accounts', 303);
        self::assertTrue($this->reload('@example.org')->isCatchAll());
    }

    public function testEditOtherMailbox(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/account/' . $this->data->bob->id);
        self::assertSelectorExists('input[name="mailbox[enabled]"]');

        $form = $crawler->selectButton('Save')->form(['mailbox[quota]' => '0']);
        $this->client->submit($form, [], self::SAME_ORIGIN);

        self::assertResponseRedirects('/manage/domain/example.com/accounts', 303);
        self::assertSame(0, $this->reload('bob@example.com')->quota);
    }

    public function testOwnMailboxCannotBeDisabledOrDeleted(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/account/' . $this->data->alice->id);
        self::assertSelectorNotExists('input[name="mailbox[enabled]"]');
        unset($crawler);

        $this->client->request('POST', '/manage/account/' . $this->data->alice->id . '/delete', ['_token' => 'irrelevant']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteMailbox(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com/accounts');
        $form = $crawler->filter('form[action="/manage/account/' . $this->data->bob->id . '/delete"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/manage/domain/example.com/accounts', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'bob@example.com');
        self::assertNull($this->accounts()->findOneByEmail('bob@example.com'));
    }

    public function testDeleteMailboxReportsDependentAccounts(): void
    {
        $catchAll = new Account(null, $this->data->exampleCom, AccountType::MailingList, null, $this->data->team->destinations->first() ?: throw new \LogicException());
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $entityManager->persist($catchAll);
        $entityManager->flush();

        // info@ forwards to alice@, and so does the new catch-all list (alice@ is the first member of team@).
        $crawler = $this->get($this->data->admin, '/manage/domain/example.com/accounts');
        $form = $crawler->filter('form[action="/manage/account/' . $this->data->alice->id . '/delete"]')->form();
        $this->client->submit($form);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'alice@example.com');
        $warnings = $this->client->getCrawler()->filter('.alert-warning')->each(static fn (Crawler $alert): string => $alert->text());
        self::assertCount(2, $warnings);
        self::assertStringStartsWith('@example.com ', $warnings[0]);
        self::assertStringStartsWith('info@example.com ', $warnings[1]);
        self::assertNull($this->accounts()->findOneByEmail('@example.com'));
        self::assertNull($this->accounts()->findOneByEmail('info@example.com'));
    }

    public function testAdminAccountCannotBeDeleted(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com/accounts');
        self::assertCount(0, $crawler->filter('form[action="/manage/account/' . $this->data->admin->id . '/delete"]'));
        self::assertSelectorTextContains('.badge.text-bg-primary', 'Administrator');

        $this->client->request('POST', '/manage/account/' . $this->data->admin->id . '/delete', ['_token' => 'irrelevant']);
        self::assertResponseStatusCodeSame(403);
        self::assertInstanceOf(Account::class, $this->accounts()->findOneByEmail('admin@example.com'));
    }

    public function testOnlyAdminsCanEditAdminAccounts(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com/accounts');
        self::assertCount(0, $crawler->filter('a[href="/manage/account/' . $this->data->admin->id . '"]'));

        $this->client->request('GET', '/manage/account/' . $this->data->admin->id);
        self::assertResponseStatusCodeSame(403);

        $this->get($this->data->admin, '/manage/account/' . $this->data->admin->id);
    }

    public function testToggleAccount(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com/accounts');
        $this->client->submit($crawler->filter('form[action="/manage/account/' . $this->data->bob->id . '/toggle"]')->form());

        self::assertResponseRedirects('/manage/domain/example.com/accounts', 303);
        self::assertFalse($this->reload('bob@example.com')->enabled);
    }

    public function testOwnAndAdminAccountsCannotBeToggled(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com/accounts');
        self::assertCount(0, $crawler->filter('form[action="/manage/account/' . $this->data->alice->id . '/toggle"]'));
        self::assertCount(0, $crawler->filter('form[action="/manage/account/' . $this->data->admin->id . '/toggle"]'));

        foreach ([$this->data->alice, $this->data->admin] as $account) {
            $this->client->request('POST', '/manage/account/' . $account->id . '/toggle', ['_token' => 'irrelevant']);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testCreateAndEditSendOnlyAddress(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com/new/sendonly');
        $this->client->submit($crawler->selectButton('Save')->form([
            'send_only[username]' => 'newsletter',
            'send_only[plainPassword]' => 'correct horse battery staple'
        ]), [], self::SAME_ORIGIN);
        self::assertResponseRedirects('/manage/domain/example.com/accounts', 303);
        $newsletter = $this->reload('newsletter@example.com');
        self::assertTrue($newsletter->isSendOnly());

        $crawler = $this->get($this->data->alice, '/manage/account/' . $newsletter->id);
        self::assertSelectorNotExists('input[name="send_only[quota]"]');
        $this->client->submit($crawler->selectButton('Save')->form(['send_only[label]' => 'Newsletter']), [], self::SAME_ORIGIN);
        self::assertSame('Newsletter', $this->reload('newsletter@example.com')->label);
    }

    public function testCannotManageOtherDomainsAccounts(): void
    {
        $this->client->loginUser($this->data->carol);
        $this->client->request('GET', '/manage/account/' . $this->data->bob->id);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/manage/domain/example.com/new/mailbox');
        self::assertResponseStatusCodeSame(403);
    }

    public function testMailingListsAreNotEditedHere(): void
    {
        $this->client->loginUser($this->data->alice);
        $this->client->request('GET', '/manage/account/' . $this->data->team->id);
        self::assertResponseStatusCodeSame(404);
    }

    private function get(Account $user, string $uri): Crawler
    {
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * Returns the texts of a column of the (first) data table.
     *
     * @return list<string>
     */
    private function column(Crawler $crawler, int $index): array
    {
        return array_values($crawler->filter('[data-controller="datatable"] > table')->first()
            ->filter('tbody tr')->each(static fn (Crawler $row): string => trim($row->filter('td')->eq($index)->text())));
    }

    private function accounts(): AccountRepository
    {
        $this->entityManager->clear();
        $repository = $this->entityManager->getRepository(Account::class);
        \assert($repository instanceof AccountRepository);

        return $repository;
    }

    private function reload(string $email): Account
    {
        $account = $this->accounts()->findOneByEmail($email);
        self::assertInstanceOf(Account::class, $account);

        return $account;
    }
}
