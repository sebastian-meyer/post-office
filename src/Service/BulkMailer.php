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
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends an email from the current user to several recipients, with the user's own credentials.
 *
 * The mail server is the one configured in MAILER_DSN, but the user's email address and password are used to log in
 * (see SmtpSubmissionTransportFactory, which refuses to send without a successful login). That way, the mail server's
 * usual rules apply: the user can only send from their own address, and the email is signed and filtered like any
 * other email of the user.
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

        try {
            $transport = $this->transportFactory->create($sender, $password);
        } catch (TransportExceptionInterface $exception) {
            throw new MailDeliveryException(0, $exception);
        }

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
            if ($transport instanceof SmtpTransport) {
                $transport->stop();
            }
        }

        return $delivered;
    }

    /**
     * @param SubmissionTransportFactory $transportFactory The factory for the connection to the mail server.
     */
    public function __construct(
        private readonly SubmissionTransportFactory $transportFactory
    ) {
    }
}
