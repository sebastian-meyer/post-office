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
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\Auth;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Connects to the submission port of the mail server in MAILER_DSN and logs in with the user's credentials.
 *
 * Any credentials in MAILER_DSN are ignored. Sending is refused unless the mail server actually checked the user's
 * credentials: otherwise (e.g. on port 25, or with "null://null") the emails would be sent without the mail server's
 * rules for authenticated users, or not at all.
 */
#[AsAlias(SubmissionTransportFactory::class)]
final class SmtpSubmissionTransportFactory implements SubmissionTransportFactory
{
    /**
     * Connects and logs in to the mail server as the user.
     *
     * @see SubmissionTransportFactory::create()
     */
    #[\Override]
    public function create(Account $sender, #[\SensitiveParameter] string $password): TransportInterface
    {
        $configured = Dsn::fromString($this->dsn);

        if (!\in_array($configured->getScheme(), ['smtp', 'smtps'], true)) {
            throw new TransportException('MAILER_DSN must point to the submission port of the mail server (smtp:// or smtps://).');
        }

        $query = parse_url($this->dsn, PHP_URL_QUERY);
        $options = [];
        parse_str(\is_string($query) ? $query : '', $options);

        $transport = $this->transportFactory->fromDsnObject(new Dsn(
            $configured->getScheme(),
            $configured->getHost(),
            $sender->getUserIdentifier(),
            $password,
            $configured->getPort(),
            $options
        ));

        if (!$transport instanceof EsmtpTransport) {
            throw new TransportException('MAILER_DSN must point to the submission port of the mail server (smtp:// or smtps://).');
        }

        $authenticators = [
            new RecordingAuthenticator(new Auth\CramMd5Authenticator()),
            new RecordingAuthenticator(new Auth\LoginAuthenticator()),
            new RecordingAuthenticator(new Auth\PlainAuthenticator())
        ];
        $transport->setAuthenticators($authenticators);
        $transport->start();

        foreach ($authenticators as $authenticator) {
            if ($authenticator->succeeded) {
                return $transport;
            }
        }

        $transport->stop();

        throw new TransportException('The mail server did not ask for a login. MAILER_DSN must point to its submission port (usually 587 or 465).');
    }

    /**
     * @param Transport $transportFactory The factory for mail transports.
     * @param string $dsn The DSN of the mail server (MAILER_DSN).
     */
    public function __construct(
        #[Autowire(service: 'mailer.transport_factory')]
        private readonly Transport $transportFactory,
        #[Autowire(env: 'MAILER_DSN')]
        #[\SensitiveParameter]
        private readonly string $dsn
    ) {
    }
}
