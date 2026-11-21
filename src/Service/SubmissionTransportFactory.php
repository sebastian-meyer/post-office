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

use App\Entity\Account;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Creates the connection to the mail server for sending emails with the user's own credentials (see BulkMailer).
 */
interface SubmissionTransportFactory
{
    /**
     * Connects and logs in to the mail server as the user.
     *
     * @param Account $sender The current user.
     * @param string $password The user's plain password.
     *
     * @return TransportInterface The connected transport.
     *
     * @throws TransportExceptionInterface if the mail server is not configured correctly, can't be reached, or
     *                                     doesn't accept the credentials.
     */
    public function create(Account $sender, #[\SensitiveParameter] string $password): TransportInterface;
}
