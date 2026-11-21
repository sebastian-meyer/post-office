<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\DatabaseTrait;
use App\Tests\Dns\FakeResolver;
use App\Tests\TestData;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DnsCheckControllerTest extends WebTestCase
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

    public function testAdminSeesDnsCheck(): void
    {
        $resolver = self::getContainer()->get(FakeResolver::class);
        \assert($resolver instanceof FakeResolver);
        $resolver->set('example.com', 'MX', ['10 mail.example.com'])
            ->set('mail.example.com', 'A', ['192.0.2.25'])
            ->set('example.com', 'TXT', ['v=spf1 mx -all']);

        $this->client->loginUser($this->data->admin);
        $crawler = $this->client->request('GET', '/manage/domain/example.com/accounts');
        self::assertCount(1, $crawler->filter('a[href="/admin/domains/example.com/dns"]'));

        $this->client->request('GET', '/admin/domains/example.com/dns');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#dns-mx .badge', 'OK');
        self::assertSelectorTextContains('#dns-mx pre', 'mail.example.com');
        self::assertSelectorTextContains('#dns-spf', 'The SPF record is valid.');
        self::assertSelectorTextContains('#dns-dmarc pre', '_dmarc.example.com. IN TXT "v=DMARC1; p=quarantine; rua=mailto:postmaster@example.com"');
        // Not configured in the test environment.
        self::assertSelectorNotExists('#dns-ptr');
        self::assertSelectorTextContains('#dns-dkim', 'DKIM_SELECTORS');
    }

    public function testDomainManagerCannotUseDnsCheck(): void
    {
        $this->client->loginUser($this->data->alice);
        $crawler = $this->client->request('GET', '/manage/domain/example.com/accounts');
        self::assertCount(0, $crawler->filter('a[href$="/dns"]'));

        $this->client->request('GET', '/admin/domains/example.com/dns');
        self::assertResponseStatusCodeSame(403);
    }
}
