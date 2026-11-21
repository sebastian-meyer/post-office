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
 * Looks up DNS records.
 */
interface DnsResolver
{
    /**
     * Looks up the records of the given type.
     *
     * A name that doesn't exist (NXDOMAIN) results in an answer without records, not in a failed answer.
     *
     * @param string $name The domain name.
     * @param string $type The record type, e.g. "MX", "TXT" or "TLSA".
     */
    public function query(string $name, string $type): DnsAnswer;
}
