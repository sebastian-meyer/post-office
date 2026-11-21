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

/**
 * Checks for DANE (TLSA records for port 25 of the mail exchangers), which lets other servers verify the certificate.
 *
 * TLSA records are only used if they are signed with DNSSEC.
 */
#[AsTaggedItem(priority: 70)]
final class DaneCheck implements DomainCheck
{
    /**
     * Checks the TLSA records of the mail exchangers.
     *
     * @see DomainCheck::check()
     */
    #[\Override]
    public function check(Domain $domain): CheckResult
    {
        $result = new CheckResult('dane');
        $hosts = array_values(array_filter($this->lookup->mxHosts($domain->domain) ?? [], static fn (string $host): bool => $host !== ''));

        if ($hosts === []) {
            return $result->add(CheckStatus::Info, 'dane.noMx');
        }

        foreach ($hosts as $host) {
            $answer = $this->lookup->resolver->query("_25._tcp.{$host}", 'TLSA');
            $result->addRecords(array_map(static fn (string $record): string => "_25._tcp.{$host} {$record}", $answer->records));

            match (true) {
                $answer->failed() => $result->add(CheckStatus::Error, 'lookupFailed'),
                $answer->records === [] => $result->add(CheckStatus::Warning, 'dane.missing', ['%host%' => $host]),
                !$answer->signed && !$answer->authenticated => $result->add(CheckStatus::Error, 'dane.unsigned', ['%host%' => $host]),
                default => $result->add(CheckStatus::Ok, 'dane.ok', ['%host%' => $host])
            };
        }

        return $result;
    }

    /**
     * @param DnsLookup $lookup The DNS lookups.
     */
    public function __construct(
        private readonly DnsLookup $lookup
    ) {
    }
}
