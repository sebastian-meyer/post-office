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
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends an email from the current user to several recipients, with the user's own credentials.
 *
 * The mail server is the one configured in MAILER_DSN, but the user's email address and password are used to log in
 * (any credentials in MAILER_DSN are ignored). That way, the mail server's usual rules apply: the user can only send
 * from their own address, and the email is signed and filtered like any other email of the user.
 *
 * The email is sent synchronously (without the message queue), so the password is never stored outside the session.
 * The recipients don't see each other: the email is addressed to the sender and sent to the recipients in batches
 * (like Bcc), each batch in one SMTP transaction. The sender gets a copy, since the email isn't saved in their "Sent"
 * folder. An email to a single recipient is addressed to that recipient directly.
 */
final class BulkMailer
{
    /**
     * The maximum number of recipients per SMTP transaction (Postfix accepts 1000 by default).
     */
    public const int BATCH_SIZE = 50;

    /**
     * Sends an email.
     *
     * @param Account $sender The current user.
     * @param string $password The user's plain password.
     * @param list<string> $recipients The email addresses of the recipients.
     * @param string $subject The subject.
     * @param string $body The plain text body.
     *
     * @return int The number of recipients (without the sender's copy).
     *
     * @throws InvalidArgumentException if there are no recipients.
     * @throws MailDeliveryException if the email could not be delivered to all recipients.
     */
    public function send(Account $sender, #[\SensitiveParameter] string $password, array $recipients, string $subject, string $body): int
    {
        $recipients = array_values(array_unique($recipients));

        if ($recipients === []) {
            throw new InvalidArgumentException('An email requires at least one recipient.');
        }

        $from = new Address($sender->getUserIdentifier(), $sender->label);
        $email = (new Email())->from($from)->subject($subject)->text($body);
        $email->to(\count($recipients) === 1 ? $recipients[0] : $from);

        $transport = $this->createTransport($sender, $password);
        $delivered = 0;

        try {
            foreach (array_chunk($recipients, self::BATCH_SIZE) as $index => $batch) {
                $envelopeRecipients = $batch;

                // The sender's copy goes with the first batch (unless the sender is a recipient anyway).
                if ($index === 0 && !\in_array($sender->getUserIdentifier(), $recipients, true)) {
                    $envelopeRecipients[] = $sender->getUserIdentifier();
                }

                $transport->send($email, new Envelope($from, array_map(Address::create(...), $envelopeRecipients)));
                $delivered += \count($batch);
            }
        } catch (TransportExceptionInterface $exception) {
            throw new MailDeliveryException($delivered, $exception);
        } finally {
            if ($transport instanceof Transport\Smtp\SmtpTransport) {
                $transport->stop();
            }
        }

        return $delivered;
    }

    /**
     * Creates the transport for the mail server in MAILER_DSN, with the user's credentials.
     *
     * @param Account $sender The current user.
     * @param string $password The user's plain password.
     */
    private function createTransport(Account $sender, #[\SensitiveParameter] string $password): TransportInterface
    {
        $configured = Dsn::fromString($this->dsn);
        $query = parse_url($this->dsn, PHP_URL_QUERY);
        $options = [];
        parse_str(\is_string($query) ? $query : '', $options);

        return $this->transportFactory->fromDsnObject(new Dsn(
            $configured->getScheme(),
            $configured->getHost(),
            $sender->getUserIdentifier(),
            $password,
            $configured->getPort(),
            $options
        ));
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
