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
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
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
 * Each recipient gets an email of their own, addressed to them, so recipients the mail server rejects don't affect the
 * others. All emails are sent over the same connection. Senders with a mailbox can get a copy at the end, since the
 * email isn't saved in their "Sent" folder.
 */
final class BulkMailer
{
    /**
     * The SMTP reply code with which the server closes the connection.
     */
    private const int SERVICE_NOT_AVAILABLE = 421;

    /**
     * Sends an email.
     *
     * @param Account $sender The current user.
     * @param string $password The user's plain password.
     * @param list<string> $recipients The email addresses of the recipients.
     * @param string $subject The subject.
     * @param string $body The plain text body.
     * @param bool $copy Whether to send a copy to the sender (ignored for senders without mailbox).
     *
     * @return BulkMailResult The recipients the email was delivered to, and those the mail server rejected.
     *
     * @throws InvalidArgumentException if there are no recipients.
     * @throws MailDeliveryException if the connection to the mail server failed (e.g. wrong password), so the email
     *                               could not be sent to the remaining recipients.
     */
    public function send(Account $sender, #[\SensitiveParameter] string $password, array $recipients, string $subject, string $body, bool $copy = false): BulkMailResult
    {
        // Addresses are compared case-insensitively, so nobody gets the email twice.
        $recipients = array_values(array_intersect_key($recipients, array_unique(array_map(strtolower(...), $recipients))));

        if ($recipients === []) {
            throw new InvalidArgumentException('An email requires at least one recipient.');
        }

        $from = new Address($sender->getUserIdentifier(), $sender->label);
        $email = (new Email())->from($from)->subject($subject)->text($body);

        try {
            $transport = $this->transportFactory->create($sender, $password);
        } catch (TransportExceptionInterface $exception) {
            throw new MailDeliveryException(0, $exception);
        }

        $delivered = [];
        $rejected = [];

        try {
            foreach ($recipients as $recipient) {
                $error = $this->sendTo($transport, $email, $from, $recipient);

                if ($error === null) {
                    $delivered[] = $recipient;
                } else {
                    $rejected[$recipient] = $error;
                }
            }

            if ($copy) {
                $this->sendCopy($transport, $email, $from, $sender, $recipients, $delivered);
            }
        } catch (TransportExceptionInterface $exception) {
            throw new MailDeliveryException(\count($delivered), $exception);
        } finally {
            if ($transport instanceof SmtpTransport) {
                $transport->stop();
            }
        }

        return new BulkMailResult($delivered, $rejected);
    }

    /**
     * Sends the email to one recipient.
     *
     * @param TransportInterface $transport The connected transport.
     * @param Email $email The email (without recipient).
     * @param Address $from The sender.
     * @param string $recipient The recipient's email address.
     *
     * @return ?string NULL if the email was sent, or the reply of the mail server if it rejected the recipient.
     *
     * @throws TransportExceptionInterface if the connection failed.
     */
    private function sendTo(TransportInterface $transport, Email $email, Address $from, string $recipient): ?string
    {
        try {
            $transport->send((clone $email)->to($recipient), new Envelope($from, [new Address($recipient)]));
        } catch (UnexpectedResponseException $exception) {
            // The server rejected this recipient, but the connection can still be used (except after 421).
            if ($exception->getCode() === self::SERVICE_NOT_AVAILABLE) {
                throw $exception;
            }

            return $exception->getMessage();
        }

        return null;
    }

    /**
     * Sends a copy to the sender, if they have a mailbox, aren't a recipient anyway, and anybody got the email.
     *
     * Send-only addresses can't receive emails. A rejected copy is ignored, since the email has been sent anyway.
     *
     * @param TransportInterface $transport The connected transport.
     * @param Email $email The email (without recipient).
     * @param Address $from The sender.
     * @param Account $sender The current user.
     * @param list<string> $recipients All recipients.
     * @param list<string> $delivered The recipients the email was delivered to.
     *
     * @throws TransportExceptionInterface if the connection failed.
     */
    private function sendCopy(TransportInterface $transport, Email $email, Address $from, Account $sender, array $recipients, array $delivered): void
    {
        $address = $sender->getUserIdentifier();

        if (!$sender->isMailbox() || $delivered === [] || \in_array($address, array_map(strtolower(...), $recipients), true)) {
            return;
        }

        $this->sendTo($transport, $email, $from, $address);
    }

    /**
     * @param SubmissionTransportFactory $transportFactory The factory for the connection to the mail server.
     */
    public function __construct(
        private readonly SubmissionTransportFactory $transportFactory
    ) {
    }
}
