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

use App\Entity\Domain;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Checks whether the DNS records of a domain are set up for sending and receiving emails with this mail server.
 */
final class DnsHealthCheck
{
    /**
     * Runs all checks.
     *
     * @param Domain $domain The domain.
     *
     * @return array<string, CheckResult> The results, by name of the check.
     */
    public function check(Domain $domain): array
    {
        $results = [];

        foreach ($this->checks as $check) {
            $result = $check->check($domain);

            if ($result !== null) {
                $results[$result->name] = $result;
            }
        }

        return $results;
    }

    /**
     * @param iterable<DomainCheck> $checks The checks.
     */
    public function __construct(
        #[AutowireIterator(DomainCheck::TAG)]
        private readonly iterable $checks
    ) {
    }
}
