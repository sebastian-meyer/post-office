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
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A check of the DNS records of a domain (see DnsHealthCheck).
 *
 * Implementations are ordered by their priority (#[AsTaggedItem]), highest first.
 */
#[AutoconfigureTag(self::TAG)]
interface DomainCheck
{
    /**
     * The service tag of the checks.
     */
    public const string TAG = 'app.dns_check';

    /**
     * Checks the domain.
     *
     * @param Domain $domain The domain.
     *
     * @return ?CheckResult The result, or NULL if the check doesn't apply (e.g. because it isn't configured).
     */
    public function check(Domain $domain): ?CheckResult;
}
