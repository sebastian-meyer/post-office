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

final class MailingListControllerTest extends WebTestCase
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

    public function testListManagerSeesMembersButNotManagers(): void
    {
        $crawler = $this->listPage($this->data->bob);

        self::assertCount(4, $this->tables($crawler)->eq(0)->filter('tbody tr'));
        self::assertSelectorNotExists('form[name="manager"]');
        self::assertCount(1, $crawler->filter('form[action$="/delete"]'));
    }

    public function testDomainManagerSeesManagers(): void
    {
        $crawler = $this->listPage($this->data->alice);

        self::assertSelectorExists('form[name="manager"]');
        self::assertStringContainsString('bob@example.com', $this->tables($crawler)->eq(1)->text());
    }

    public function testOthersCannotOpenList(): void
    {
        foreach ([$this->data->dave, $this->data->carol] as $user) {
            $this->client->loginUser($user);
            $this->client->request('GET', $this->listUrl());
            self::assertResponseStatusCodeSame(403);
        }

        // Mailboxes have no list page.
        $this->client->loginUser($this->data->admin);
        $this->client->request('GET', '/manage/list/' . $this->data->bob->id);
        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateSettings(): void
    {
        $form = $this->listPage($this->data->bob)->filter('form[name="settings"]')->form();
        $form['settings[label]'] = 'The Team';
        $form['settings[strictList]']->untick();
        $this->client->submit($form, [], self::SAME_ORIGIN);

        self::assertResponseRedirects($this->listUrl(), 303);
        $team = $this->reload('team@example.com');
        self::assertSame('The Team', $team->label);
        self::assertFalse($team->strictList);
    }

    public function testAddAndRemoveMember(): void
    {
        $form = $this->listPage($this->data->bob)->filter('form[name="member"]')->form(['member[email]' => 'new@example.net']);
        $this->client->submit($form, [], self::SAME_ORIGIN);
        self::assertResponseRedirects($this->listUrl(), 303);
        self::assertCount(5, $this->reload('team@example.com')->destinations);

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'new@example.net');
        $remove = $crawler->filter('form[action$="/members/remove"]')->reduce(
            static fn (Crawler $form): bool => $form->filter('input[name="email"]')->attr('value') === 'new@example.net'
        )->form();
        $this->client->submit($remove);

        self::assertResponseRedirects($this->listUrl(), 303);
        self::assertCount(4, $this->reload('team@example.com')->destinations);
    }

    public function testInvalidMemberShowsError(): void
    {
        $form = $this->listPage($this->data->bob)->filter('form[name="member"]')->form(['member[email]' => 'not an address']);
        $this->client->submit($form, [], self::SAME_ORIGIN);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form[name="member"] .invalid-feedback');
    }

    public function testAppointAndRemoveManager(): void
    {
        $form = $this->listPage($this->data->alice)->filter('form[name="manager"]')->form(['manager[email]' => 'alice@example.com']);
        $this->client->submit($form, [], self::SAME_ORIGIN);
        self::assertResponseRedirects($this->listUrl(), 303);
        self::assertCount(2, $this->reload('team@example.com')->managers);

        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('form[action="' . $this->listUrl() . '/managers/' . $this->data->bob->id . '/remove"]')->form());
        self::assertResponseRedirects($this->listUrl(), 303);
        self::assertCount(1, $this->reload('team@example.com')->managers);
    }

    public function testListManagerSuggestions(): void
    {
        // Enabled mailboxes of the list's domain, except bob (already a manager); not dave (other domain).
        $crawler = $this->listPage($this->data->alice);

        self::assertSame(
            ['admin@example.com', 'alice@example.com'],
            $crawler->filter('datalist#manager-candidates option')->extract(['value'])
        );
    }

    public function testManagerOfOtherDomainCanBeAppointed(): void
    {
        $form = $this->listPage($this->data->alice)->filter('form[name="manager"]')->form(['manager[email]' => 'dave@example.org']);
        $this->client->submit($form, [], self::SAME_ORIGIN);

        self::assertResponseRedirects($this->listUrl(), 303);
        self::assertSame(
            ['bob@example.com', 'dave@example.org'],
            array_map('strval', $this->reload('team@example.com')->managers->toArray())
        );
    }

    public function testIneligibleManagerShowsError(): void
    {
        $form = $this->listPage($this->data->alice)->filter('form[name="manager"]')->form(['manager[email]' => 'info@example.com']);
        $this->client->submit($form, [], self::SAME_ORIGIN);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form[name="manager"] .invalid-feedback');
    }

    public function testListManagerCannotAppointManagers(): void
    {
        $this->client->loginUser($this->data->bob);
        $this->client->request('POST', $this->listUrl() . '/managers', ['manager' => ['email' => 'alice@example.com']], [], self::SAME_ORIGIN);

        self::assertResponseStatusCodeSame(403);
    }

    public function testListManagerCanDeleteList(): void
    {
        $this->client->submit($this->listPage($this->data->bob)->filter('form[action$="/delete"]')->form());

        // bob cannot manage the domain, so he gets back to the overview.
        self::assertResponseRedirects('/manage', 303);
        self::assertNull($this->accounts()->findOneByEmail('team@example.com'));
    }

    public function testCreateMailingList(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com/new/list');
        $form = $crawler->selectButton('Save')->form([
            'mailing_list[username]' => 'news',
            'mailing_list[label]' => 'News'
        ]);
        $this->client->submit($form, [], self::SAME_ORIGIN);

        $news = $this->reload('news@example.com');
        self::assertTrue($news->isMailingList());
        self::assertResponseRedirects('/manage/list/' . $news->id, 303);
    }

    public function testDomainPageLinksToListPage(): void
    {
        $crawler = $this->get($this->data->alice, '/manage/domain/example.com/accounts');

        self::assertCount(1, $crawler->filter('a[href="' . $this->listUrl() . '"]'));
    }

    private function listUrl(): string
    {
        return '/manage/list/' . $this->data->team->id;
    }

    private function listPage(Account $user): Crawler
    {
        return $this->get($user, $this->listUrl());
    }

    private function get(Account $user, string $uri): Crawler
    {
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    private function tables(Crawler $crawler): Crawler
    {
        return $crawler->filter('[data-controller="datatable"] > table');
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
