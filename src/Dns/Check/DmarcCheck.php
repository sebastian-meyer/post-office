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
 * Checks the DMARC record, which tells other servers what to do with emails failing SPF and DKIM.
 */
#[AsTaggedItem(priority: 40)]
final class DmarcCheck implements DomainCheck
{
    /**
     * Checks the DMARC record.
     *
     * @see DomainCheck::check()
     */
    #[\Override]
    public function check(Domain $domain): CheckResult
    {
        $result = new CheckResult('dmarc');
        $name = "_dmarc.{$domain->domain}";
        $records = $this->lookup->txt($name, 'v=DMARC1');

        if ($records === null) {
            return $result->add(CheckStatus::Error, 'lookupFailed');
        }

        $result->addRecords($records);

        if (\count($records) !== 1) {
            $result->add($records === [] ? CheckStatus::Warning : CheckStatus::Error, $records === [] ? 'dmarc.missing' : 'dmarc.multiple');

            return $result->suggest($name, 'TXT', "v=DMARC1; p=quarantine; rua=mailto:postmaster@{$domain->domain}");
        }

        preg_match('/;\s*p\s*=\s*([a-z]+)/i', $records[0], $matches);
        $policy = strtolower($matches[1] ?? '');

        return match ($policy) {
            'quarantine', 'reject' => $result->add(CheckStatus::Ok, 'dmarc.ok', ['%policy%' => $policy]),
            'none' => $result->add(CheckStatus::Warning, 'dmarc.monitoring'),
            default => $result->add(CheckStatus::Error, 'dmarc.invalid')
        };
    }

    /**
     * @param DnsLookup $lookup The DNS lookups.
     */
    public function __construct(
        private readonly DnsLookup $lookup
    ) {
    }
}
