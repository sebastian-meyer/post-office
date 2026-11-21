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
use App\Dns\DnsResolver;
use App\Dns\DomainCheck;
use App\Entity\Domain;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Checks whether the domain's zone is signed with DNSSEC, which is required for DANE.
 *
 * The answer is "validated" only if the resolver checks the signatures (AD flag). Otherwise it can only be seen that
 * the zone is signed, which happens if the resolver used by the Post Office doesn't validate DNSSEC.
 */
#[AsTaggedItem(priority: 80)]
final class DnssecCheck implements DomainCheck
{
    /**
     * Checks the signatures of the MX records.
     *
     * @see DomainCheck::check()
     */
    #[\Override]
    public function check(Domain $domain): CheckResult
    {
        $result = new CheckResult('dnssec');
        $answer = $this->resolver->query($domain->domain, 'MX');

        return match (true) {
            $answer->failed() => $result->add(CheckStatus::Error, 'lookupFailed'),
            $answer->authenticated => $result->add(CheckStatus::Ok, 'dnssec.validated'),
            $answer->signed => $result->add(CheckStatus::Warning, 'dnssec.notValidated'),
            $answer->records === [] => $result->add(CheckStatus::Info, 'dnssec.unknown'),
            default => $result->add(CheckStatus::Warning, 'dnssec.unsigned')
        };
    }

    /**
     * @param DnsResolver $resolver The DNS resolver.
     */
    public function __construct(
        private readonly DnsResolver $resolver
    ) {
    }
}
