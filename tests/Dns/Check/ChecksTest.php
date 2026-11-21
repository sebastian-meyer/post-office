<?php

declare(strict_types=1);

namespace App\Tests\Dns\Check;

use App\Dns\Check\DaneCheck;
use App\Dns\Check\DkimCheck;
use App\Dns\Check\DmarcCheck;
use App\Dns\Check\DnssecCheck;
use App\Dns\Check\MtaStsCheck;
use App\Dns\Check\MxCheck;
use App\Dns\Check\ReverseDnsCheck;
use App\Dns\Check\SpfCheck;
use App\Dns\Check\TlsRptCheck;
use App\Dns\CheckResult;
use App\Dns\CheckStatus;
use App\Dns\DnsLookup;
use App\Dns\SpfEvaluator;
use App\Entity\Domain;
use App\Tests\Dns\FakeResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ChecksTest extends TestCase
{
    private const string HOSTNAME = 'mail.example.net';

    private FakeResolver $resolver;

    private DnsLookup $lookup;

    private Domain $domain;

    protected function setUp(): void
    {
        $this->resolver = (new FakeResolver())
            ->set('example.com', 'MX', ['10 mail.example.net'], signed: true, authenticated: true)
            ->set('mail.example.net', 'A', ['192.0.2.25'])
            ->set('mail.example.net', 'AAAA', ['2001:db8::25']);
        $this->lookup = new DnsLookup($this->resolver);
        $this->domain = new Domain('example.com');
    }

    public function testMx(): void
    {
        $this->assertResult(CheckStatus::Ok, ['mx.ok'], (new MxCheck($this->lookup, self::HOSTNAME))->check($this->domain));

        $this->resolver->set('example.com', 'MX', ['20 backup.example.org', '10 mx.example.org'])->set('mx.example.org', 'A', ['198.51.100.1']);
        $result = (new MxCheck($this->lookup, self::HOSTNAME))->check($this->domain);
        $this->assertResult(CheckStatus::Error, ['mx.unresolvable', 'mx.foreign'], $result);
        self::assertSame(['mx.example.org', 'backup.example.org'], $result->records);
        self::assertSame(['name' => 'example.com', 'type' => 'MX', 'value' => '10 mail.example.net.'], $result->suggestion);

        $this->resolver->set('example.com', 'MX', ['0 .']);
        $result = (new MxCheck($this->lookup))->check($this->domain);
        $this->assertResult(CheckStatus::Error, ['mx.null'], $result);
        self::assertSame(['.'], $result->records);

        $this->resolver->set('example.com', 'MX', []);
        $result = (new MxCheck($this->lookup))->check($this->domain);
        $this->assertResult(CheckStatus::Error, ['mx.missing'], $result);
        self::assertNull($result->suggestion);

        $this->resolver->fail('example.com', 'MX');
        $this->assertResult(CheckStatus::Error, ['lookupFailed'], (new MxCheck($this->lookup))->check($this->domain));
    }

    public function testReverseDns(): void
    {
        self::assertNull((new ReverseDnsCheck($this->lookup))->check($this->domain));

        $this->resolver->set('25.2.0.192.in-addr.arpa', 'PTR', ['mail.example.net']);
        $result = (new ReverseDnsCheck($this->lookup, self::HOSTNAME))->check($this->domain);
        self::assertInstanceOf(CheckResult::class, $result);
        $this->assertResult(CheckStatus::Error, ['ptr.mismatch'], $result);

        $this->resolver->set('5.2.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2.ip6.arpa', 'PTR', ['MAIL.example.net']);
        $result = (new ReverseDnsCheck($this->lookup, self::HOSTNAME))->check($this->domain);
        self::assertInstanceOf(CheckResult::class, $result);
        $this->assertResult(CheckStatus::Ok, ['ptr.ok'], $result);
    }

    public function testDnssec(): void
    {
        $this->assertResult(CheckStatus::Ok, ['dnssec.validated'], (new DnssecCheck($this->resolver))->check($this->domain));

        $this->resolver->set('example.com', 'MX', ['10 mail.example.net'], signed: true);
        $this->assertResult(CheckStatus::Warning, ['dnssec.notValidated'], (new DnssecCheck($this->resolver))->check($this->domain));

        $this->resolver->set('example.com', 'MX', ['10 mail.example.net']);
        $this->assertResult(CheckStatus::Warning, ['dnssec.unsigned'], (new DnssecCheck($this->resolver))->check($this->domain));
    }

    public function testDane(): void
    {
        $this->resolver->set('example.com', 'MX', ['10 mail.example.net', '20 mx2.example.net']);
        $this->resolver->set('_25._tcp.mail.example.net', 'TLSA', ['3 1 1 abcdef'], signed: true);
        $this->resolver->set('_25._tcp.mx2.example.net', 'TLSA', ['3 1 1 abcdef']);

        $result = (new DaneCheck($this->lookup))->check($this->domain);
        $this->assertResult(CheckStatus::Error, ['dane.ok', 'dane.unsigned'], $result);
        self::assertSame(['_25._tcp.mail.example.net 3 1 1 abcdef', '_25._tcp.mx2.example.net 3 1 1 abcdef'], $result->records);

        $this->resolver->set('_25._tcp.mx2.example.net', 'TLSA', []);
        $this->assertResult(CheckStatus::Warning, ['dane.ok', 'dane.missing'], (new DaneCheck($this->lookup))->check($this->domain));
    }

    public function testSpf(): void
    {
        $check = new SpfCheck($this->lookup, new SpfEvaluator($this->lookup), self::HOSTNAME);

        $result = $check->check($this->domain);
        $this->assertResult(CheckStatus::Error, ['spf.missing'], $result);
        self::assertSame('v=spf1 mx -all', $result->suggestion['value'] ?? null);

        $this->resolver->set('example.com', 'TXT', ['v=spf1 mx -all']);
        $this->assertResult(CheckStatus::Ok, ['spf.ok'], $check->check($this->domain));

        $this->resolver->set('example.com', 'TXT', ['v=spf1 ip4:192.0.2.25 ?all']);
        $this->assertResult(CheckStatus::Error, ['spf.weak', 'spf.serverNotAllowed'], $check->check($this->domain));

        $this->resolver->set('example.com', 'TXT', ['v=spf1 +all']);
        $this->assertResult(CheckStatus::Error, ['spf.passAll'], $check->check($this->domain));

        $this->resolver->set('example.com', 'TXT', ['v=spf1 mx -all', 'v=spf1 -all']);
        $this->assertResult(CheckStatus::Error, ['spf.multiple'], $check->check($this->domain));
    }

    public function testDkim(): void
    {
        $this->assertResult(CheckStatus::Info, ['dkim.notConfigured'], (new DkimCheck($this->lookup, ['']))->check($this->domain));

        $this->resolver->set('mail._domainkey.example.com', 'TXT', ['v=DKIM1; k=rsa; p=MIIBIjAN']);
        $this->resolver->set('old._domainkey.example.com', 'TXT', ['v=DKIM1; p=']);
        $this->assertResult(CheckStatus::Ok, ['dkim.ok'], (new DkimCheck($this->lookup, ['mail']))->check($this->domain));
        $this->assertResult(CheckStatus::Error, ['dkim.revoked'], (new DkimCheck($this->lookup, ['old']))->check($this->domain));
        // One valid key is enough.
        $this->assertResult(CheckStatus::Info, ['dkim.ok', 'dkim.missing'], (new DkimCheck($this->lookup, ['mail', 'new']))->check($this->domain));
    }

    public function testDmarc(): void
    {
        $check = new DmarcCheck($this->lookup);
        $result = $check->check($this->domain);
        $this->assertResult(CheckStatus::Warning, ['dmarc.missing'], $result);
        self::assertSame('_dmarc.example.com', $result->suggestion['name'] ?? null);

        $this->resolver->set('_dmarc.example.com', 'TXT', ['v=DMARC1; p=none; rua=mailto:dmarc@example.com']);
        $this->assertResult(CheckStatus::Warning, ['dmarc.monitoring'], $check->check($this->domain));

        $this->resolver->set('_dmarc.example.com', 'TXT', ['v=DMARC1;p=Reject']);
        $this->assertResult(CheckStatus::Ok, ['dmarc.ok'], $check->check($this->domain));
    }

    public function testMtaSts(): void
    {
        $check = fn (string $policy): CheckResult => (new MtaStsCheck($this->lookup, new MockHttpClient(new MockResponse($policy))))->check($this->domain);

        $this->assertResult(CheckStatus::Info, ['mtasts.missing'], $check(''));

        $this->resolver->set('_mta-sts.example.com', 'TXT', ['v=STSv1; id=20261121']);
        $this->assertResult(CheckStatus::Ok, ['mtasts.ok'], $check("version: STSv1\r\nmode: enforce\r\nmx: *.example.net\r\nmax_age: 604800\r\n"));
        $this->assertResult(CheckStatus::Error, ['mtasts.mxMismatch'], $check("version: STSv1\nmode: enforce\nmx: mx.example.org\nmax_age: 604800\n"));
        $this->assertResult(CheckStatus::Info, ['mtasts.testing'], $check("version: STSv1\nmode: testing\nmx: mail.example.net\n"));
        $this->assertResult(CheckStatus::Error, ['mtasts.invalidPolicy'], $check("version: STSv2\nmode: enforce\n"));

        $unavailable = (new MtaStsCheck($this->lookup, new MockHttpClient(new MockResponse('', ['http_code' => 404]))))->check($this->domain);
        $this->assertResult(CheckStatus::Error, ['mtasts.policyUnavailable'], $unavailable);
    }

    public function testTlsRpt(): void
    {
        $this->assertResult(CheckStatus::Info, ['tlsrpt.missing'], (new TlsRptCheck($this->lookup))->check($this->domain));

        $this->resolver->set('_smtp._tls.example.com', 'TXT', ['v=TLSRPTv1; rua=mailto:tls@example.com']);
        $this->assertResult(CheckStatus::Ok, ['tlsrpt.ok'], (new TlsRptCheck($this->lookup))->check($this->domain));
    }

    /**
     * @param list<string> $messages The expected findings (translation keys without "dns.").
     */
    private function assertResult(CheckStatus $status, array $messages, CheckResult $result): void
    {
        self::assertSame(
            array_map(static fn (string $message): string => 'dns.' . $message, $messages),
            array_map(static fn (array $finding): string => $finding['message']->getMessage(), $result->findings)
        );
        self::assertSame($status, $result->status);
    }
}
