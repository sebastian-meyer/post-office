<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Account;
use App\Tests\DatabaseTrait;
use App\Tests\TestData;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class AccountControllerTest extends WebTestCase
{
    use DatabaseTrait;

    private KernelBrowser $client;

    private TestData $data;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->data = (new TestData())->persist($this->startDatabase());
    }

    public function testMembershipTableIsADataTable(): void
    {
        $this->membershipTable($this->data->dave);
        $wrapper = $this->client->getCrawler()->filter('[data-controller="datatable"]');

        // The controller must be on a wrapper, not on the table itself (which DataTables moves around).
        self::assertSame('div', $wrapper->nodeName());
        $language = json_decode((string) $wrapper->attr('data-datatable-language-value'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($language);
        self::assertArrayHasKey('search', $language);
    }

    public function testMembershipTableListsOwnListsOnly(): void
    {
        // dave@example.org is a member of team@example.com only
        $rows = $this->membershipTable($this->data->dave)->filter('tbody tr');
        self::assertCount(1, $rows);
        self::assertStringContainsString('team@example.com', $rows->text());
        self::assertCount(1, $rows->filter('form[action="/account/lists/' . $this->data->team->id . '/leave"]'));

        // carol@example.org is not a member of any list, so the table isn't shown at all
        self::assertCount(0, $this->membershipTable($this->data->carol));
    }

    public function testMemberCanLeaveList(): void
    {
        $form = $this->membershipTable($this->data->dave)->filter('tbody form')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/account', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'team@example.com');
        self::assertCount(0, $this->membershipTable($this->data->dave));
        self::assertCount(3, $this->reloadTeam()->destinations);
    }

    public function testUserCanChangeTheirName(): void
    {
        $this->client->loginUser($this->data->dave);
        $crawler = $this->client->request('GET', '/account');
        $this->client->submit($crawler->filter('form[name="form"]')->form(['form[label]' => 'Dave Example']), [], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseRedirects('/account', 303);
        $this->entityManager->clear();
        self::assertSame('Dave Example', $this->entityManager->find(Account::class, $this->data->dave->id)?->label);
    }

    public function testLeavingRequiresValidToken(): void
    {
        $this->client->loginUser($this->data->dave);
        $this->client->request('POST', '/account/lists/' . $this->data->team->id . '/leave', ['_token' => 'invalid']);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(4, $this->reloadTeam()->destinations);
    }

    public function testNonMemberCannotLeaveList(): void
    {
        $this->client->loginUser($this->data->carol);
        $this->client->request('POST', '/account/lists/' . $this->data->team->id . '/leave', ['_token' => 'irrelevant']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Loads the account page as the given user and returns the membership table.
     */
    private function membershipTable(Account $user): Crawler
    {
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();

        return $crawler->filter('[data-controller="datatable"] > table');
    }

    /**
     * Reloads the mailing list from the database (the entity manager is cleared between requests).
     */
    private function reloadTeam(): Account
    {
        $this->entityManager->clear();
        $team = $this->entityManager->find(Account::class, $this->data->team->id);
        self::assertInstanceOf(Account::class, $team);

        return $team;
    }
}
