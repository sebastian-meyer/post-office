<?php

/**
 * Post Office
 * Copyright (C) 2026 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Dns;

use NetDNS2\ENUM\Error;
use NetDNS2\Exception;
use NetDNS2\Resolver;
use NetDNS2\RR;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Looks up DNS records with Net_DNS2, which supports all record types (e.g. TLSA) and DNSSEC.
 *
 * The name servers are taken from DNS_RESOLVERS, or from /etc/resolv.conf if that is empty. Answers are kept for the
 * rest of the request, since several checks look up the same records.
 */
#[AsAlias(DnsResolver::class)]
final class NetDns2Resolver implements DnsResolver, ResetInterface
{
    /**
     * The supported record types.
     */
    private const array TYPES = [
        'A' => RR\A::class,
        'AAAA' => RR\AAAA::class,
        'MX' => RR\MX::class,
        'PTR' => RR\PTR::class,
        'TLSA' => RR\TLSA::class,
        'TXT' => RR\TXT::class
    ];

    /**
     * The timeout per query in seconds.
     */
    private const float TIMEOUT = 3.0;

    /**
     * The answers so far (by type and name).
     *
     * @var array<string, DnsAnswer>
     */
    private array $answers = [];

    /**
     * Looks up the records of the given type.
     *
     * @see DnsResolver::query()
     */
    #[\Override]
    public function query(string $name, string $type): DnsAnswer
    {
        $type = strtoupper($type);
        $name = rtrim(strtolower($name), '.');

        return $this->answers["{$type} {$name}"] ??= $this->lookup($name, $type);
    }

    /**
     * Forgets the answers (e.g. between requests in long-running processes).
     *
     * @see ResetInterface::reset()
     */
    #[\Override]
    public function reset(): void
    {
        $this->answers = [];
    }

    /**
     * Queries the name servers.
     *
     * @param string $name The domain name.
     * @param string $type The record type.
     */
    private function lookup(string $name, string $type): DnsAnswer
    {
        $class = self::TYPES[$type] ?? null;

        if ($class === null) {
            return new DnsAnswer(error: \sprintf('Unsupported record type "%s".', $type));
        }

        $nameservers = array_values(array_filter(array_map(trim(...), $this->nameservers), static fn (string $nameserver): bool => $nameserver !== ''));

        $resolver = null;

        try {
            $resolver = new Resolver($nameservers !== [] ? ['nameservers' => $nameservers] : null);
            $resolver->timeout = self::TIMEOUT;
            // Request the signatures, which tell whether the zone is signed.
            $resolver->dnssec = true;

            $response = $resolver->query($name, $type);
        } catch (Exception $exception) {
            // Net_DNS2 tries the next name server after NXDOMAIN, and throws the error of the last one it tried. So a
            // definite "doesn't exist" of one server can be hidden behind e.g. a timeout of another one.
            foreach ([$exception, ...($resolver->last_exception_list ?? [])] as $error) {
                if ($error->getCode() === Error::DNS_NXDOMAIN->value) {
                    return new DnsAnswer();
                }
            }

            return new DnsAnswer(error: $exception->getMessage());
        }

        $records = [];
        $signed = false;

        foreach ($response->answer as $record) {
            if ($record instanceof RR\RRSIG) {
                $signed = true;
            } elseif ($record instanceof $class) {
                $records[] = $this->format($record);
            }
        }

        return new DnsAnswer($records, $signed, $response->header->ad === 1);
    }

    /**
     * Returns the record's data in presentation format.
     *
     * @param RR $record The record.
     */
    private function format(RR $record): string
    {
        // "owner TTL class type data"
        $data = preg_split('/\s+/', trim((string) $record), 5)[4] ?? '';

        if ($record instanceof RR\TXT) {
            // The data are quoted strings, which are concatenated without spaces (see RFC 7208, section 3.3).
            preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $data, $matches);

            return implode('', array_map(static fn (string $part): string => str_replace('\\"', '"', $part), $matches[1]));
        }

        if ($record instanceof RR\A || $record instanceof RR\AAAA) {
            // Use the canonical (shortest) notation, e.g. "2001:db8::1" instead of "2001:db8:0:0:0:0:0:1".
            $address = filter_var($data, FILTER_VALIDATE_IP) !== false ? inet_pton($data) : false;

            return $address !== false ? (string) inet_ntop($address) : $data;
        }

        return preg_replace('/\.(?=\s|$)/', '', $data) ?? $data;
    }

    /**
     * @param list<string> $nameservers The IP addresses of the name servers (DNS_RESOLVERS).
     */
    public function __construct(
        #[Autowire(param: 'app.dns_resolvers')]
        private readonly array $nameservers = []
    ) {
    }
}
