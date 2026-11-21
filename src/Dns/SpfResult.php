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
 * Whether an SPF record authorizes an IP address to send emails (see SpfEvaluator).
 */
enum SpfResult
{
    /**
     * The record authorizes the address ("pass").
     */
    case Pass;

    /**
     * The record doesn't authorize the address ("fail", "softfail", "neutral" or no record).
     */
    case NoPass;

    /**
     * The record can't be evaluated here (e.g. because of "exists", "ptr", macros or a failed lookup).
     */
    case Unknown;

    /**
     * The record needs more than 10 DNS lookups, which receivers treat as a permanent error (RFC 7208, 4.6.4).
     */
    case TooManyLookups;
}
