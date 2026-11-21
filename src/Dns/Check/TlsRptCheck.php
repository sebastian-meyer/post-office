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
 * Checks the optional TLS-RPT record, which asks other servers to report TLS problems (RFC 8460).
 */
#[AsTaggedItem(priority: 20)]
final class TlsRptCheck implements DomainCheck
{
    /**
     * Checks the TLS-RPT record.
     *
     * @see DomainCheck::check()
     */
    #[\Override]
    public function check(Domain $domain): CheckResult
    {
        $result = new CheckResult('tlsrpt');
        $name = "_smtp._tls.{$domain->domain}";
        $records = $this->lookup->txt($name, 'v=TLSRPTv1');

        if ($records === null) {
            return $result->add(CheckStatus::Error, 'lookupFailed');
        }

        $result->addRecords($records);

        return match (\count($records)) {
            0 => $result->add(CheckStatus::Info, 'tlsrpt.missing')->suggest($name, 'TXT', "v=TLSRPTv1; rua=mailto:postmaster@{$domain->domain}"),
            1 => $result->add(CheckStatus::Ok, 'tlsrpt.ok'),
            default => $result->add(CheckStatus::Error, 'tlsrpt.multiple')
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
