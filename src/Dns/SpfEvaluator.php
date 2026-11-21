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
 * Evaluates SPF records (RFC 7208) to find out whether an IP address may send emails for a domain.
 *
 * Only the mechanisms needed to answer that question are supported: "all", "ip4", "ip6", "a", "mx", "include" and the
 * "redirect" modifier. Records with "exists", "ptr" or macros can't be evaluated (SpfResult::Unknown).
 */
final class SpfEvaluator
{
    /**
     * The maximum number of mechanisms and modifiers that cause DNS lookups (RFC 7208, section 4.6.4).
     */
    private const int MAX_LOOKUPS = 10;

    /**
     * The result of firstMatch() if a mechanism can't be evaluated.
     */
    private const string UNKNOWN = 'unknown';

    /**
     * Whether the SPF record of the domain authorizes the IP address.
     *
     * @param string $domain The domain.
     * @param string $address The IP address of the sending server.
     */
    public function authorizes(string $domain, string $address): SpfResult
    {
        $lookups = 0;

        try {
            return match ($this->evaluateRecord($domain, $address, $lookups)) {
                true => SpfResult::Pass,
                false => SpfResult::NoPass,
                null => SpfResult::Unknown
            };
        } catch (SpfLookupLimitExceeded) {
            return SpfResult::TooManyLookups;
        }
    }

    /**
     * Evaluates the SPF record of a domain.
     *
     * @param string $domain The domain.
     * @param string $address The IP address.
     * @param int $lookups The number of DNS lookups so far.
     */
    private function evaluateRecord(string $domain, string $address, int &$lookups): ?bool
    {
        $records = $this->lookup->txt($domain, 'v=spf1');

        // Without exactly one record, the result is "none" or "permerror".
        if ($records === null || \count($records) !== 1) {
            return null;
        }

        $terms = \array_slice(explode(' ', preg_replace('/\s+/', ' ', trim($records[0])) ?? ''), 1);
        $qualifier = $this->firstMatch($terms, $domain, $address, $lookups);

        if ($qualifier !== '') {
            return $qualifier === self::UNKNOWN ? null : $qualifier === '+';
        }

        return $this->redirect($terms, $address, $lookups);
    }

    /**
     * Evaluates the record the "redirect" modifier points to, if there is one.
     *
     * @param list<string> $terms The terms of the SPF record (without version).
     * @param string $address The IP address.
     * @param int $lookups The number of DNS lookups so far.
     *
     * @return ?bool The result of the redirect, FALSE without redirect, or NULL if it can't be evaluated.
     */
    private function redirect(array $terms, string $address, int &$lookups): ?bool
    {
        foreach ($terms as $term) {
            if (preg_match('/^redirect=(.+)$/i', $term, $matches) === 1) {
                $this->countLookup($lookups);

                return $this->evaluateRecord($matches[1], $address, $lookups);
            }
        }

        return false;
    }

    /**
     * Returns the qualifier of the first mechanism matching the IP address.
     *
     * @param list<string> $terms The terms of the SPF record (without version).
     * @param string $domain The domain of the record.
     * @param string $address The IP address.
     * @param int $lookups The number of DNS lookups so far.
     *
     * @return string The qualifier ("+", "-", "~" or "?"), an empty string if no mechanism matches, or UNKNOWN if a
     *                mechanism can't be evaluated.
     */
    private function firstMatch(array $terms, string $domain, string $address, int &$lookups): string
    {
        foreach ($terms as $term) {
            // Skip modifiers (e.g. "redirect=" or "exp=").
            if (preg_match('/^[a-z][a-z0-9_.-]*=/i', $term) === 1) {
                continue;
            }

            $match = $this->matches(ltrim($term, '+-~?'), $domain, $address, $lookups);

            if ($match !== false) {
                return $match === null ? self::UNKNOWN : (\in_array($term[0] ?? '', ['+', '-', '~', '?'], true) ? $term[0] : '+');
            }
        }

        return '';
    }

    /**
     * Whether a mechanism matches the IP address.
     *
     * @param string $mechanism The mechanism without qualifier.
     * @param string $domain The domain of the record.
     * @param string $address The IP address.
     * @param int $lookups The number of DNS lookups so far.
     *
     * @return ?bool Whether it matches, or NULL if it can't be evaluated.
     */
    private function matches(string $mechanism, string $domain, string $address, int &$lookups): ?bool
    {
        if (preg_match('/^(all|ip4|ip6|a|mx|include)(?::([^\/]+))?(?:\/(\d+))?(?:\/\/(\d+))?$/i', $mechanism, $parts) !== 1) {
            // "exists", "ptr", macros or invalid mechanisms.
            return null;
        }

        $name = strtolower($parts[1]);
        $target = ($parts[2] ?? '') !== '' ? $parts[2] : $domain;

        if ($name === 'all') {
            return true;
        }

        if ($name === 'ip4' || $name === 'ip6') {
            return $this->inRange($address, $target, $parts[3] ?? '');
        }

        $this->countLookup($lookups);

        return match ($name) {
            'include' => $this->evaluateRecord($target, $address, $lookups),
            'a' => $this->matchesHosts([$target], $address, $parts[3] ?? '', $parts[4] ?? ''),
            default => $this->matchesHosts($this->lookup->mxHosts($target), $address, $parts[3] ?? '', $parts[4] ?? '')
        };
    }

    /**
     * Counts a mechanism or modifier causing DNS lookups.
     *
     * @param int $lookups The number of DNS lookups so far.
     *
     * @throws SpfLookupLimitExceeded if there are more than MAX_LOOKUPS.
     */
    private function countLookup(int &$lookups): void
    {
        if (++$lookups > self::MAX_LOOKUPS) {
            throw new SpfLookupLimitExceeded();
        }
    }

    /**
     * Whether one of the hosts' addresses matches the IP address.
     *
     * @param ?list<string> $hosts The host names, or NULL if their lookup failed.
     * @param string $address The IP address.
     * @param string $ipv4Prefix The prefix length for IPv4 addresses (or empty).
     * @param string $ipv6Prefix The prefix length for IPv6 addresses (or empty).
     *
     * @return ?bool Whether one of the addresses matches, or NULL if a lookup failed.
     */
    private function matchesHosts(?array $hosts, string $address, string $ipv4Prefix, string $ipv6Prefix): ?bool
    {
        if ($hosts === null) {
            return null;
        }

        foreach ($hosts as $host) {
            $hostAddresses = $this->lookup->addresses($host);

            if ($hostAddresses === null) {
                return null;
            }

            foreach ($hostAddresses as $hostAddress) {
                if ($this->inRange($address, $hostAddress, str_contains($hostAddress, ':') ? $ipv6Prefix : $ipv4Prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the IP address is in the network.
     *
     * @param string $address The IP address.
     * @param string $network The network address.
     * @param string $prefix The prefix length (or empty for a single address).
     */
    private function inRange(string $address, string $network, string $prefix): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP) === false || filter_var($network, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $addressBytes = inet_pton($address);
        $networkBytes = inet_pton($network);

        if ($addressBytes === false || $networkBytes === false || \strlen($addressBytes) !== \strlen($networkBytes)) {
            return false;
        }

        $bits = $prefix === '' ? \strlen($addressBytes) * 8 : min((int) $prefix, \strlen($addressBytes) * 8);
        $bytes = max(0, intdiv($bits, 8));

        if (strncmp($addressBytes, $networkBytes, $bytes) !== 0) {
            return false;
        }

        $mask = $bits % 8 === 0 ? 0 : (0xFF << (8 - $bits % 8)) & 0xFF;

        return $mask === 0 || (\ord($addressBytes[$bytes]) & $mask) === (\ord($networkBytes[$bytes]) & $mask);
    }

    /**
     * @param DnsLookup $lookup The DNS lookups.
     */
    public function __construct(
        private readonly DnsLookup $lookup
    ) {
    }
}
