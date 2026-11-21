<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Service\BulkMailer;
use App\Service\MailDeliveryException;
use App\Tests\TestData;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class BulkMailerTest extends KernelTestCase
{
    public function testEachRecipientGetsAnEmailOfTheirOwn(): void
    {
        $mailer = self::getContainer()->get(BulkMailer::class);
        \assert($mailer instanceof BulkMailer);

        $result = $mailer->send((new TestData())->alice, 'secret', ['a@example.net', 'b@example.net', 'A@example.net'], 'Hello', 'Hello world', true);

        self::assertSame(['a@example.net', 'b@example.net'], $result->delivered);
        self::assertSame([], $result->rejected);
        // One email per recipient (duplicates ignoring case removed), plus the sender's copy at the end.
        self::assertEmailCount(3);
        $addressed = array_map(
            static fn (MessageEvent $event): array => [
                self::addresses($event->getEnvelope()->getRecipients()),
                self::addresses($event->getMessage() instanceof Email ? $event->getMessage()->getTo() : [])
            ],
            self::getMailerEvents()
        );
        self::assertSame([
            [['a@example.net'], ['a@example.net']],
            [['b@example.net'], ['b@example.net']],
            [['alice@example.com'], ['alice@example.com']]
        ], $addressed);
        self::assertSame('alice@example.com', self::getMailerEvent(0)->getEnvelope()->getSender()->getAddress());
    }

    public function testNoCopyUnlessRequested(): void
    {
        $transport = new RejectingTransport();
        (new BulkMailer($transport))->send((new TestData())->alice, 'secret', ['a@example.net', 'b@example.net'], 'Hello', 'Hello world');

        self::assertCount(2, $transport->sent);
    }

    public function testRejectedRecipientsDontAffectOthers(): void
    {
        $transport = new RejectingTransport();
        $result = (new BulkMailer($transport))->send((new TestData())->alice, 'secret', ['a@example.net', 'unknown@example.com', 'b@example.net'], 'Hello', 'Hello world', true);

        self::assertSame(['a@example.net', 'b@example.net'], $result->delivered);
        self::assertSame(['unknown@example.com'], array_keys($result->rejected));
        self::assertStringContainsString('Recipient address rejected', $result->rejected['unknown@example.com']);
        self::assertCount(3, $transport->sent);
    }

    public function testClosedConnectionAbortsSending(): void
    {
        $transport = new RejectingTransport();

        try {
            (new BulkMailer($transport))->send((new TestData())->alice, 'secret', ['a@example.net', 'closing@example.net', 'b@example.net'], 'Hello', 'Hello world');
            self::fail('The closed connection was not reported.');
        } catch (MailDeliveryException $exception) {
            self::assertSame(1, $exception->delivered);
            self::assertCount(1, $transport->sent);
        }
    }

    public function testNoCopyIfNobodyGotTheEmail(): void
    {
        $transport = new RejectingTransport();
        $result = (new BulkMailer($transport))->send((new TestData())->alice, 'secret', ['unknown@example.com'], 'Hello', 'Hello world', true);

        self::assertSame([], $result->delivered);
        self::assertSame([], $transport->sent);
    }

    public function testSendOnlySenderGetsNoCopy(): void
    {
        $data = new TestData();
        $sender = new Account('noreply', $data->exampleCom, AccountType::SendOnly);
        $mailer = self::getContainer()->get(BulkMailer::class);
        \assert($mailer instanceof BulkMailer);

        $mailer->send($sender, 'secret', ['a@example.net', 'b@example.net'], 'Hello', 'Hello world', true);

        $recipients = array_map(static fn (MessageEvent $event): array => self::addresses($event->getEnvelope()->getRecipients()), self::getMailerEvents());
        self::assertSame([['a@example.net'], ['b@example.net']], $recipients);
    }

    public function testRecipientsAreRequired(): void
    {
        $mailer = self::getContainer()->get(BulkMailer::class);
        \assert($mailer instanceof BulkMailer);

        $this->expectException(InvalidArgumentException::class);
        $mailer->send((new TestData())->alice, 'secret', [], 'Hello', 'Hello world');
    }

    public function testAuthenticationFailureIsDetected(): void
    {
        $failure = new MailDeliveryException(0, new TransportException('Failed to authenticate on SMTP server.', 535));
        $other = new MailDeliveryException(3, new TransportException('Recipient address rejected.', 554));

        self::assertTrue($failure->isAuthenticationFailure());
        self::assertFalse($other->isAuthenticationFailure());
        self::assertSame(3, $other->delivered);
    }

    /**
     * @param array<Address> $addresses
     *
     * @return list<string>
     */
    private static function addresses(array $addresses): array
    {
        return array_values(array_map(static fn (Address $address): string => $address->getAddress(), $addresses));
    }
}
