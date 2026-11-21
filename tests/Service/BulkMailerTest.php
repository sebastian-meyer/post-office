<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\BulkMailer;
use App\Service\MailDeliveryException;
use App\Tests\TestData;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\TransportException;

final class BulkMailerTest extends KernelTestCase
{
    public function testRecipientsAreSentInBatches(): void
    {
        $recipients = array_map(static fn (int $i): string => "user{$i}@example.net", range(1, BulkMailer::BATCH_SIZE * 2 + 1));
        $mailer = self::getContainer()->get(BulkMailer::class);
        \assert($mailer instanceof BulkMailer);

        self::assertSame(\count($recipients), $mailer->send((new TestData())->alice, 'secret', [...$recipients, 'user1@example.net'], 'Hello', 'Hello world'));
        self::assertEmailCount(3);

        $counts = array_map(
            static fn (MessageEvent $event): int => \count($event->getEnvelope()->getRecipients()),
            self::getMailerEvents()
        );
        // The first batch includes the sender's copy.
        self::assertSame([BulkMailer::BATCH_SIZE + 1, BulkMailer::BATCH_SIZE, 1], $counts);
        self::assertSame('alice@example.com', self::getMailerEvent(0)->getEnvelope()->getSender()->getAddress());
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
}
