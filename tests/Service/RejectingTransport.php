<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Service\SubmissionTransportFactory;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Rejects recipients like a mail server: addresses starting with "unknown" with 550 (the connection can be reused),
 * addresses starting with "closing" with 421 (the server closes the connection). Records all accepted emails.
 */
final class RejectingTransport implements SubmissionTransportFactory, TransportInterface
{
    /**
     * @var list<SentMessage>
     */
    public array $sent = [];

    public function create(Account $sender, #[\SensitiveParameter] string $password): TransportInterface
    {
        return $this;
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $recipient = $envelope?->getRecipients()[0]->getAddress() ?? '';

        if (str_starts_with($recipient, 'unknown')) {
            throw new UnexpectedResponseException("Expected response code \"250/251/252\" but got code \"550\", with message \"550 5.1.1 <{$recipient}>: Recipient address rejected\".", 550);
        }

        if (str_starts_with($recipient, 'closing')) {
            throw new UnexpectedResponseException('Expected response code "250/251/252" but got code "421", with message "421 Service not available".', 421);
        }

        return $this->sent[] = new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'rejecting://';
    }
}
