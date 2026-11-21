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

use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Thrown when an email could not be delivered to (all of) its recipients (see BulkMailer).
 */
final class MailDeliveryException extends RuntimeException
{
    /**
     * The SMTP reply code for failed authentication.
     */
    private const int AUTHENTICATION_FAILED = 535;

    /**
     * Whether the mail server rejected the user's credentials.
     */
    public function isAuthenticationFailure(): bool
    {
        return $this->getCode() === self::AUTHENTICATION_FAILED;
    }

    /**
     * @param int $delivered The number of recipients the email was delivered to before the error.
     * @param TransportExceptionInterface $previous The error of the mail transport.
     */
    public function __construct(
        public readonly int $delivered,
        TransportExceptionInterface $previous
    ) {
        parent::__construct($previous->getMessage(), $previous->getCode(), $previous);
    }
}
