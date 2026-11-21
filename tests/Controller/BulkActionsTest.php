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

final class BulkActionsTest extends WebTestCase
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

    public function testDisableMarkedAccounts(): void
    {
        // Alice cannot disable herself or the administrator, and accounts of other domains are ignored.
        $this->submit($this->data->alice, '/manage/domain/example.com/accounts/disable', [
            $this->data->bob, $this->data->info, $this->data->alice, $this->data->admin, $this->data->carol
        ]);

        self::assertResponseRedirects('/manage/domain/example.com/accounts', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', '2 accounts have been disabled: bob@example.com, info@example.com');
        self::assertSelectorTextContains('.alert-warning', 'admin@example.com, alice@example.com');

        self::assertFalse($this->reload('bob@example.com')->enabled);
        self::assertFalse($this->reload('info@example.com')->enabled);
        self::assertTrue($this->reload('alice@example.com')->enabled);
        self::assertTrue($this->reload('admin@example.com')->enabled);
        self::assertTrue($this->reload('carol@example.org')->enabled);
    }

    public function testEnableMarkedAccounts(): void
    {
        $this->submit($this->data->alice, '/manage/domain/example.com/accounts/disable', [$this->data->bob]);
        $this->submit($this->data->alice, '/manage/domain/example.com/accounts/enable', [$this->data->bob]);

        self::assertResponseRedirects('/manage/domain/example.com/accounts', 303);
        self::assertTrue($this->reload('bob@example.com')->enabled);
    }

    public function testDeleteMarkedAccounts(): void
    {
        // info@ forwards to alice@, so it is deleted with her (before its own turn comes).
        $this->submit($this->data->admin, '/manage/domain/example.com/accounts/delete', [$this->data->alice, $this->data->info, $this->data->admin]);

        self::assertResponseRedirects('/manage/domain/example.com/accounts', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'alice@example.com');
        self::assertSelectorExists('.alert-warning');
        self::assertNull($this->accounts()->findOneByEmail('alice@example.com'));
        self::assertNull($this->accounts()->findOneByEmail('info@example.com'));
        self::assertInstanceOf(Account::class, $this->accounts()->findOneByEmail('admin@example.com'));
    }

    public function testNothingMarked(): void
    {
        $this->submit($this->data->alice, '/manage/domain/example.com/accounts/delete', []);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'No entries have been marked.');
    }

    public function testBulkActionsRequireValidToken(): void
    {
        $this->client->loginUser($this->data->alice);
        $this->client->request('POST', '/manage/domain/example.com/accounts/delete', ['_token' => 'invalid', 'selection' => [(string) $this->data->bob->id]]);

        self::assertResponseStatusCodeSame(403);
        self::assertInstanceOf(Account::class, $this->accounts()->findOneByEmail('bob@example.com'));
    }

    public function testBulkActionsRequireDomainManager(): void
    {
        $this->client->loginUser($this->data->carol);
        $this->client->request('POST', '/manage/domain/example.com/accounts/delete', ['_token' => 'irrelevant', 'selection' => [(string) $this->data->bob->id]]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRemoveMarkedMembers(): void
    {
        $listId = (string) $this->data->team->id;
        $this->client->loginUser($this->data->bob);
        $crawler = $this->client->request('GET', '/manage/list/' . $listId);
        $token = $this->token($crawler, '/manage/list/' . $listId . '/members/remove-marked');

        $this->client->request('POST', '/manage/list/' . $listId . '/members/remove-marked', [
            '_token' => $token,
            'selection' => ['bob@example.com', 'EXTERNAL@example.net', 'stranger@example.net']
        ]);

        self::assertResponseRedirects('/manage/list/' . $listId, 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', '2 members have been removed');
        self::assertSame(['alice@example.com', 'dave@example.org'], array_map(strval(...), $this->reload('team@example.com')->destinations->getValues()));
    }

    /**
     * Submits a bulk action with the token from the domain page.
     *
     * @param list<Account> $accounts The marked accounts.
     */
    private function submit(Account $user, string $uri, array $accounts): void
    {
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/manage/domain/example.com/accounts');

        $this->client->request('POST', $uri, [
            '_token' => $this->token($crawler, $uri),
            'selection' => array_map(static fn (Account $account): string => (string) $account->id, $accounts)
        ]);
    }

    private function token(Crawler $crawler, string $action): string
    {
        return (string) $crawler->filter('form[action="' . $action . '"] input[name="_token"]')->attr('value');
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
        return $this->accounts()->findOneByEmail($email) ?? throw new \LogicException(\sprintf('Account "%s" not found.', $email));
    }
}
