<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Account;
use App\Repository\AccountRepository;
use App\Tests\DatabaseTrait;
use App\Tests\TestData;
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
        $this->client->request('GET', '/account');
        self::assertSelectorNotExists('a[href="/manage"]');

        $this->client->request('GET', '/manage');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDomainManagerSeesOwnDomainOnly(): void
    {
        $crawler = $this->get($this->data->alice, '/manage');
        self::assertSelectorExists('a[href="/manage"]');
        self::assertSame(['example.com'], $this->column($crawler, 0));

        $this->client->request('GET', '/manage/domain/example.org');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminSeesAllDomains(): void
    {
        self::assertSame(['example.com', 'example.org'], $this->column($this->get($this->data->admin, '/manage'), 0));
        $this->client->request('GET', '/manage/domain/example.org');
        self::assertResponseIsSuccessful();
    }

    public function testListManagerSeesTheirLists(): void
    {
        $crawler = $this->get($this->data->bob, '/manage');
        self::assertStringContainsString('team@example.com', $crawler->filter('table')->text());

        $this->client->request('GET', '/manage/domain/example.com');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDomainPageListsAccounts(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com');
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

        self::assertResponseRedirects('/manage/domain/example.com', 303);
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

        self::assertResponseRedirects('/manage/domain/example.org', 303);
        self::assertTrue($this->reload('@example.org')->isCatchAll());
    }

    public function testEditOtherMailbox(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/account/' . $this->data->bob->id);
        self::assertSelectorExists('input[name="mailbox[enabled]"]');

        $form = $crawler->selectButton('Save')->form(['mailbox[quota]' => '0']);
        $this->client->submit($form, [], self::SAME_ORIGIN);

        self::assertResponseRedirects('/manage/domain/example.com', 303);
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
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com');
        $form = $crawler->filter('form[action="/manage/account/' . $this->data->bob->id . '/delete"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/manage/domain/example.com', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'bob@example.com');
        self::assertNull($this->accounts()->findOneByEmail('bob@example.com'));
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
