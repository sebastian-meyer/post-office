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

namespace App\Service;

/**
 * The result of sending an email to several recipients (see BulkMailer).
 */
final class BulkMailResult
{
    /**
     * @param list<string> $delivered The recipients the mail server accepted the email for.
     * @param array<string, string> $rejected The recipients the mail server rejected, with its reply.
     */
    public function __construct(
        public readonly array $delivered,
        public readonly array $rejected
    ) {
    }
}
