<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Account;
use App\Entity\Blacklist;
use App\Entity\Domain;
use App\Entity\TlsPolicy;
use App\Tests\DatabaseTrait;
use App\Tests\TestData;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class AdminControllerTest extends WebTestCase
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

    public function testAdminAreaIsForAdminsOnly(): void
    {
        $this->client->loginUser($this->data->alice);

        foreach (['/admin/tls-policies', '/admin/blacklist', '/admin/domains/new'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(403, $uri);
        }

        $crawler = $this->client->request('GET', '/manage/domain/example.com/accounts');
        self::assertCount(0, $crawler->filter('form[name="manager"]'));
        $this->client->request('POST', '/manage/domain/example.com/managers', ['manager' => ['email' => 'bob@example.com']], [], self::SAME_ORIGIN);
        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateAndDeleteDomain(): void
    {
        $crawler = $this->get('/admin/domains/new');
        $this->client->submit($crawler->selectButton('Save')->form(['domain[domain]' => 'Example.NET']), [], self::SAME_ORIGIN);
        self::assertResponseRedirects('/manage/domain/example.net/accounts', 303);
        self::assertInstanceOf(Domain::class, $this->find(Domain::class, 'example.net'));

        $crawler = $this->get('/manage');
        $this->client->submit($crawler->filter('form[action="/admin/domains/example.net/delete"]')->form());
        self::assertResponseRedirects('/manage', 303);
        self::assertNull($this->find(Domain::class, 'example.net'));
    }

    public function testDuplicateDomainIsRejected(): void
    {
        $crawler = $this->get('/admin/domains/new');
        $this->client->submit($crawler->selectButton('Save')->form(['domain[domain]' => 'example.com']), [], self::SAME_ORIGIN);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback');
    }

    public function testDomainWithAccountsCannotBeDeleted(): void
    {
        // Domains with accounts have no delete button.
        $crawler = $this->get('/manage');
        self::assertCount(0, $crawler->filter('form[action="/admin/domains/example.com/delete"]'));

        // Even if an account is added after the page was loaded, the domain is not deleted.
        $domain = new Domain('example.net');
        $this->entityManager->persist($domain);
        $this->entityManager->flush();
        $form = $this->get('/manage')->filter('form[action="/admin/domains/example.net/delete"]')->form();

        $domain = $this->find(Domain::class, 'example.net');
        self::assertInstanceOf(Domain::class, $domain);
        $this->entityManager->persist(new Account('frank', $domain));
        $this->entityManager->flush();

        $this->client->submit($form);
        self::assertResponseRedirects('/manage', 303);
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
        self::assertInstanceOf(Domain::class, $this->find(Domain::class, 'example.net'));
    }

    public function testAppointAndRemoveDomainManager(): void
    {
        $crawler = $this->get('/manage/domain/example.com/accounts');
        $form = $crawler->filter('form[name="manager"]')->form(['manager[email]' => 'bob@example.com']);
        $this->client->submit($form, [], self::SAME_ORIGIN);
        self::assertResponseRedirects('/manage/domain/example.com/accounts', 303);

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'bob@example.com');
        $this->client->submit($crawler->filter('form[action="/manage/domain/example.com/managers/' . $this->data->bob->id . '/remove"]')->form());
        self::assertResponseRedirects('/manage/domain/example.com/accounts', 303);

        $domain = $this->find(Domain::class, 'example.com');
        self::assertInstanceOf(Domain::class, $domain);
        self::assertSame(['alice@example.com'], array_map('strval', $domain->managers->toArray()));
    }

    public function testDomainManagerSuggestions(): void
    {
        $this->data->bob->disable();
        $this->entityManager->flush();

        // Only enabled mailboxes of the domain, which are not yet managers (alice is), are suggested.
        $crawler = $this->get('/manage/domain/example.com/accounts');
        self::assertSame('manager-candidates', $crawler->filter('input[name="manager[email]"]')->attr('list'));
        self::assertSame(['admin@example.com'], $crawler->filter('datalist#manager-candidates option')->extract(['value']));
    }

    public function testIneligibleDomainManagerShowsError(): void
    {
        $crawler = $this->get('/manage/domain/example.com/accounts');
        $this->client->submit($crawler->filter('form[name="manager"]')->form(['manager[email]' => 'carol@example.org']), [], self::SAME_ORIGIN);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form[name="manager"] .invalid-feedback');
    }

    public function testTlsPolicyLifecycle(): void
    {
        $crawler = $this->get('/admin/tls-policies/new');
        $this->client->submit($crawler->selectButton('Save')->form([
            'tls_policy[domain]' => '.Example.NET',
            'tls_policy[policy]' => 'dane-only',
            'tls_policy[params]' => ''
        ]), [], self::SAME_ORIGIN);
        self::assertResponseRedirects('/admin/tls-policies', 303);

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('.example.net', $crawler->filter('table')->text());

        $crawler = $this->get('/admin/tls-policies/edit?domain=.example.net');
        self::assertSelectorNotExists('input[name="tls_policy[domain]"]');
        $form = $crawler->selectButton('Save')->form(['tls_policy[policy]' => 'secure', 'tls_policy[params]' => 'match=nexthop']);
        $form['tls_policy[enabled]']->untick();
        $this->client->submit($form, [], self::SAME_ORIGIN);

        $policy = $this->find(TlsPolicy::class, '.example.net');
        self::assertInstanceOf(TlsPolicy::class, $policy);
        self::assertSame('secure', $policy->policy);
        self::assertSame('match=nexthop', $policy->params);
        self::assertFalse($policy->enabled);

        $crawler = $this->get('/admin/tls-policies');
        // The domain starts with a dot, so it must not be part of the URL path (web servers block paths with "/.").
        self::assertSame(0, $crawler->filter('a[href*="/."], form[action*="/."]')->count());
        $this->client->submit($crawler->filter('form[action="/admin/tls-policies/delete"]')->reduce(
            static fn (Crawler $form): bool => $form->filter('input[name="domain"]')->attr('value') === '.example.net'
        )->form());
        self::assertResponseRedirects('/admin/tls-policies', 303);
        self::assertNull($this->find(TlsPolicy::class, '.example.net'));
    }

    public function testToggleTlsPolicyAndBlacklistEntry(): void
    {
        $policy = new TlsPolicy('.example.net');
        $entry = new Blacklist('spammer@example.net');
        $this->entityManager->persist($policy);
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        $crawler = $this->get('/admin/tls-policies');
        $this->client->submit($crawler->filter('form[action="/admin/tls-policies/toggle"]')->form());
        self::assertResponseRedirects('/admin/tls-policies', 303);
        self::assertFalse($this->find(TlsPolicy::class, '.example.net')?->enabled);

        $crawler = $this->get('/admin/blacklist');
        $this->client->submit($crawler->filter('form[action="/admin/blacklist/' . $entry->id . '/toggle"]')->form());
        self::assertResponseRedirects('/admin/blacklist', 303);
        self::assertFalse($this->find(Blacklist::class, $entry->id)?->enabled);
    }

    public function testInvalidTlsPolicyDomainIsRejected(): void
    {
        $crawler = $this->get('/admin/tls-policies/new');
        $this->client->submit($crawler->selectButton('Save')->form(['tls_policy[domain]' => 'not a domain']), [], self::SAME_ORIGIN);

        self::assertResponseStatusCodeSame(422);
    }

    public function testBlacklistLifecycle(): void
    {
        $crawler = $this->get('/admin/blacklist/new');
        $this->client->submit($crawler->selectButton('Save')->form(['blacklist[address]' => 'spammer@example.net', 'blacklist[action]' => 'DISCARD']), [], self::SAME_ORIGIN);
        self::assertResponseRedirects('/admin/blacklist', 303);

        $entry = $this->entityManager->getRepository(Blacklist::class)->findOneBy(['username' => 'spammer']);
        self::assertInstanceOf(Blacklist::class, $entry);
        self::assertSame('DISCARD', $entry->action);

        // Adding the same address again is rejected.
        $crawler = $this->get('/admin/blacklist/new');
        $this->client->submit($crawler->selectButton('Save')->form(['blacklist[address]' => 'spammer@example.net']), [], self::SAME_ORIGIN);
        self::assertResponseStatusCodeSame(422);

        $crawler = $this->get('/admin/blacklist/' . $entry->id . '/edit');
        $this->client->submit($crawler->selectButton('Save')->form(['blacklist[action]' => 'REJECT']), [], self::SAME_ORIGIN);
        self::assertSame('REJECT', $this->find(Blacklist::class, $entry->id)?->action);

        $crawler = $this->get('/admin/blacklist');
        $this->client->submit($crawler->filter('form[action="/admin/blacklist/' . $entry->id . '/delete"]')->form());
        self::assertResponseRedirects('/admin/blacklist', 303);
        self::assertNull($this->find(Blacklist::class, $entry->id));
    }

    public function testInvalidBlacklistAddressIsRejected(): void
    {
        $crawler = $this->get('/admin/blacklist/new');
        $this->client->submit($crawler->selectButton('Save')->form(['blacklist[address]' => 'not valid']), [], self::SAME_ORIGIN);

        self::assertResponseStatusCodeSame(422);
    }

    public function testHomePageShowsCardsByRole(): void
    {
        $this->client->loginUser($this->data->dave);
        $crawler = $this->client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'de']);
        self::assertSame('de', $crawler->filter('html')->attr('lang'));
        self::assertCount(0, $crawler->filter('a.card[href="/manage"]'));
        self::assertCount(1, $crawler->filter('a.card[href="/account"]'));

        $crawler = $this->get('/');
        self::assertCount(1, $crawler->filter('a.card[href="/manage"]'));
        self::assertCount(1, $crawler->filter('a.card[href="/admin/tls-policies"]'));
    }

    public function testWelcomeMessageUsesLabel(): void
    {
        // Without a label, the email address is used.
        $this->client->loginUser($this->data->dave);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('h1', 'dave@example.org');

        $this->data->alice->setLabel('Alice Example');
        $this->entityManager->flush();
        $this->client->loginUser($this->data->alice);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('h1', 'Alice Example');
        self::assertSelectorTextNotContains('h1', 'alice@example.com');
    }

    /**
     * Loads a page as administrator.
     */
    private function get(string $uri): Crawler
    {
        $this->client->loginUser($this->data->admin);
        $crawler = $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * Finds an entity in the database (the entity manager is cleared between requests).
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ?T
     */
    private function find(string $class, int|string $id): ?object
    {
        $this->entityManager->clear();

        return $this->entityManager->find($class, $id);
    }
}
