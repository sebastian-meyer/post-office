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

use Symfony\Component\Mailer\Transport\Smtp\Auth\AuthenticatorInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * Records whether an SMTP authenticator succeeded (see SmtpSubmissionTransportFactory).
 *
 * Symfony's SMTP transport silently skips authentication if the server doesn't offer it, which would send emails
 * without checking the user's credentials.
 */
final class RecordingAuthenticator implements AuthenticatorInterface
{
    /**
     * Whether the authentication succeeded.
     */
    public private(set) bool $succeeded = false;

    /**
     * Authenticates with the wrapped authenticator and records the success.
     *
     * @see AuthenticatorInterface::authenticate()
     */
    #[\Override]
    public function authenticate(EsmtpTransport $client): void
    {
        $this->authenticator->authenticate($client);
        $this->succeeded = true;
    }

    /**
     * Returns the keyword of the wrapped authenticator.
     *
     * @see AuthenticatorInterface::getAuthKeyword()
     */
    #[\Override]
    public function getAuthKeyword(): string
    {
        return $this->authenticator->getAuthKeyword();
    }

    /**
     * @param AuthenticatorInterface $authenticator The authenticator to wrap.
     */
    public function __construct(
        private readonly AuthenticatorInterface $authenticator
    ) {
    }
}
