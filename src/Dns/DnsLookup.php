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

/**
 * Typed lookups of the DNS records needed by the checks (see DnsHealthCheck).
 */
final class DnsLookup
{
    /**
     * Returns the A and AAAA records of a host.
     *
     * @param string $host The host name.
     *
     * @return list<string> The IP addresses (empty if the lookups failed).
     */
    public function addresses(string $host): array
    {
        return [...$this->resolver->query($host, 'A')->records, ...$this->resolver->query($host, 'AAAA')->records];
    }

    /**
     * Returns the mail exchangers of a domain, ordered by preference.
     *
     * A "null MX" record (RFC 7505), which states that the domain doesn't accept emails, results in an empty host name.
     *
     * @param string $domain The domain name.
     *
     * @return ?list<string> The host names, or NULL if the lookup failed.
     */
    public function mxHosts(string $domain): ?array
    {
        $answer = $this->resolver->query($domain, 'MX');

        if ($answer->failed()) {
            return null;
        }

        $hosts = [];

        foreach ($answer->records as $record) {
            [$preference, $host] = explode(' ', $record, 2) + [1 => ''];
            $hosts[] = ['preference' => (int) $preference, 'host' => strtolower(rtrim(trim($host), '.'))];
        }

        usort($hosts, static fn (array $first, array $second): int => $first['preference'] <=> $second['preference']);

        return array_values(array_unique(array_column($hosts, 'host')));
    }

    /**
     * Returns the TXT records of a name which start with the given tag (e.g. "v=spf1"), ignoring case.
     *
     * @param string $name The domain name.
     * @param string $tag The version tag the records must start with.
     *
     * @return ?list<string> The records, or NULL if the lookup failed.
     */
    public function txt(string $name, string $tag): ?array
    {
        $answer = $this->resolver->query($name, 'TXT');

        if ($answer->failed()) {
            return null;
        }

        return array_values(array_filter(
            $answer->records,
            static fn (string $record): bool => preg_match('/^' . preg_quote($tag, '/') . '(\s|;|$)/i', $record) === 1
        ));
    }

    /**
     * @param DnsResolver $resolver The DNS resolver.
     */
    public function __construct(
        public readonly DnsResolver $resolver
    ) {
    }
}
