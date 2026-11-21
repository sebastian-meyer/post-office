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
 * The answer to a DNS query.
 */
final class DnsAnswer
{
    /**
     * Whether the lookup failed (e.g. timeout or SERVFAIL), so nothing is known about the records.
     */
    public function failed(): bool
    {
        return $this->error !== null;
    }

    /**
     * @param list<string> $records The records of the requested type in presentation format, without the owner name,
     *                              e.g. "10 mx.example.com" for MX, or the concatenated text of a TXT record.
     *                              Domain names have no trailing dot.
     * @param bool $signed Whether the answer contains DNSSEC signatures (the zone is signed).
     * @param bool $authenticated Whether the resolver validated the answer with DNSSEC (AD flag).
     * @param ?string $error The error message, if the lookup failed.
     */
    public function __construct(
        public readonly array $records = [],
        public readonly bool $signed = false,
        public readonly bool $authenticated = false,
        public readonly ?string $error = null
    ) {
    }
}
