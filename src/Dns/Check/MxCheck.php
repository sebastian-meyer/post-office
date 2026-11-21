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

namespace App\Dns\Check;

use App\Dns\CheckResult;
use App\Dns\CheckStatus;
use App\Dns\DnsLookup;
use App\Dns\DomainCheck;
use App\Entity\Domain;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Checks that the domain's MX records point to resolvable hosts, and to this mail server (if MAIL_HOSTNAME is set).
 */
#[AsTaggedItem(priority: 100)]
final class MxCheck implements DomainCheck
{
    /**
     * Checks the MX records.
     *
     * @see DomainCheck::check()
     */
    #[\Override]
    public function check(Domain $domain): CheckResult
    {
        $result = new CheckResult('mx');
        $hosts = $this->lookup->mxHosts($domain->domain);

        if ($hosts === null) {
            return $result->add(CheckStatus::Error, 'lookupFailed');
        }

        // A null MX record has no host name, it is written as ".".
        $result->addRecords(array_map(static fn (string $host): string => $host !== '' ? $host : '.', $hosts));

        if ($hosts === [] || $hosts === ['']) {
            $result->add(CheckStatus::Error, $hosts === [] ? 'mx.missing' : 'mx.null');

            return $this->suggest($result, $domain);
        }

        $this->verifyAddresses($result, $hosts);

        if (!$this->pointsHere($hosts)) {
            $result->add(CheckStatus::Error, 'mx.foreign', ['%hostname%' => strtolower(rtrim(trim($this->mailHostname), '.'))]);

            return $this->suggest($result, $domain);
        }

        return $result->status === CheckStatus::Ok ? $result->add(CheckStatus::Ok, 'mx.ok') : $result;
    }

    /**
     * Whether one of the mail exchangers is this mail server (always TRUE if MAIL_HOSTNAME is not set).
     *
     * @param list<string> $hosts The mail exchangers.
     */
    private function pointsHere(array $hosts): bool
    {
        $hostname = strtolower(rtrim(trim($this->mailHostname), '.'));

        return $hostname === '' || \in_array($hostname, $hosts, true);
    }

    /**
     * Checks that the mail exchangers have IP addresses.
     *
     * @param CheckResult $result The result.
     * @param list<string> $hosts The mail exchangers.
     */
    private function verifyAddresses(CheckResult $result, array $hosts): void
    {
        foreach ($hosts as $host) {
            $addresses = $this->lookup->addresses($host);

            if ($addresses === null) {
                $result->add(CheckStatus::Error, 'lookupFailed');
            } elseif ($addresses === []) {
                $result->add(CheckStatus::Error, 'mx.unresolvable', ['%host%' => $host]);
            }
        }
    }

    /**
     * Suggests an MX record pointing to this mail server.
     *
     * @param CheckResult $result The result.
     * @param Domain $domain The domain.
     */
    private function suggest(CheckResult $result, Domain $domain): CheckResult
    {
        $hostname = rtrim(trim($this->mailHostname), '.');

        return $hostname !== '' ? $result->suggest($domain->domain, 'MX', "10 {$hostname}.") : $result;
    }

    /**
     * @param DnsLookup $lookup The DNS lookups.
     * @param string $mailHostname The host name of this mail server (MAIL_HOSTNAME), or empty.
     */
    public function __construct(
        private readonly DnsLookup $lookup,
        #[Autowire(param: 'app.mail_hostname')]
        private readonly string $mailHostname = ''
    ) {
    }
}
