<?php

declare(strict_types=1);

namespace App\Tests\Dns;

use App\Dns\DnsLookup;
use App\Dns\SpfEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpfEvaluatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, ?bool}>
     */
    public static function records(): iterable
    {
        yield 'ip4 network' => ['v=spf1 ip4:192.0.2.0/24 -all', '192.0.2.10', true];
        yield 'ip4 outside network' => ['v=spf1 ip4:192.0.2.0/25 -all', '192.0.2.200', false];
        yield 'ip6 network' => ['v=spf1 ip6:2001:db8::/32 -all', '2001:db8:1::25', true];
        yield 'a of the domain' => ['v=spf1 a -all', '198.51.100.1', true];
        yield 'a with prefix' => ['v=spf1 a:other.example/24 -all', '203.0.113.99', true];
        yield 'mx' => ['v=spf1 mx -all', '198.51.100.25', true];
        yield 'include' => ['v=spf1 include:_spf.example.net -all', '192.0.2.77', true];
        yield 'redirect' => ['v=spf1 redirect=_spf.example.net', '192.0.2.77', true];
        yield 'softfail is no pass' => ['v=spf1 ~all', '192.0.2.1', false];
        yield 'explicit fail' => ['v=spf1 -ip4:192.0.2.1 +all', '192.0.2.1', false];
        yield 'exists cannot be evaluated' => ['v=spf1 exists:%{i}.example.net -all', '192.0.2.1', null];
        yield 'match before exists' => ['v=spf1 ip4:192.0.2.1 exists:x.example.net -all', '192.0.2.1', true];
    }

    #[DataProvider('records')]
    public function testAuthorizes(string $record, string $address, ?bool $expected): void
    {
        $resolver = (new FakeResolver())
            ->set('example.com', 'TXT', ['some-verification=123', $record])
            ->set('example.com', 'A', ['198.51.100.1'])
            ->set('example.com', 'MX', ['10 mx.example.com'])
            ->set('mx.example.com', 'A', ['198.51.100.25'])
            ->set('other.example', 'A', ['203.0.113.1'])
            ->set('_spf.example.net', 'TXT', ['v=spf1 ip4:192.0.2.77 -all']);

        self::assertSame($expected, (new SpfEvaluator(new DnsLookup($resolver)))->authorizes('example.com', $address));
    }

    public function testMultipleRecordsCannotBeEvaluated(): void
    {
        $resolver = (new FakeResolver())->set('example.com', 'TXT', ['v=spf1 -all', 'v=spf1 +all']);

        self::assertNull((new SpfEvaluator(new DnsLookup($resolver)))->authorizes('example.com', '192.0.2.1'));
    }

    public function testIncludeLoopIsStopped(): void
    {
        $resolver = (new FakeResolver())->set('example.com', 'TXT', ['v=spf1 include:example.com -all']);

        self::assertNull((new SpfEvaluator(new DnsLookup($resolver)))->authorizes('example.com', '192.0.2.1'));
    }
}
