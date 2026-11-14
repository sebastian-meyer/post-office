<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Destination;
use App\Entity\Domain;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    private Domain $domain;

    protected function setUp(): void
    {
        $this->domain = new Domain('example.com');
    }

    public function testNewAccountIsMailboxByDefault(): void
    {
        $account = new Account('alice', $this->domain);

        self::assertSame(AccountType::Mailbox, $account->type);
        self::assertTrue($account->isMailbox());
        self::assertSame(Account::DEFAULT_QUOTA, $account->quota);
        self::assertCount(0, $account->destinations);
    }

    public function testAliasHasExactlyOneDestinationAndNoQuota(): void
    {
        $account = new Account('info', $this->domain, AccountType::Alias, null, new Destination('alice@example.com'));

        self::assertTrue($account->isAlias());
        self::assertNull($account->quota);
        self::assertCount(1, $account->destinations);
    }

    /**
     * @return iterable<string, array{AccountType, int}>
     */
    public static function invalidDestinationCounts(): iterable
    {
        yield 'alias without destination' => [AccountType::Alias, 0];
        yield 'alias with two destinations' => [AccountType::Alias, 2];
        yield 'mailbox with destination' => [AccountType::Mailbox, 1];
        yield 'send-only with destination' => [AccountType::SendOnly, 1];
    }

    #[DataProvider('invalidDestinationCounts')]
    public function testConstructorRejectsInvalidDestinationCount(AccountType $type, int $count): void
    {
        $destinations = [];

        for ($i = 1; $i <= $count; ++$i) {
            $destinations[] = new Destination("member{$i}@example.net");
        }

        $this->expectException(InvalidArgumentException::class);

        new Account('test', $this->domain, $type, null, ...$destinations);
    }

    public function testMailingListStaysListWhenMembersAreRemoved(): void
    {
        $alice = new Destination('alice@example.com');
        $bob = new Destination('bob@example.com');
        $list = new Account('team', $this->domain, AccountType::MailingList, null, $alice, $bob);
        $list->setReplyToList(true)->setStrictList(true);

        $list->removeDestination($bob);
        self::assertTrue($list->isMailingList());

        $list->removeDestination($alice);
        self::assertTrue($list->isMailingList());
        self::assertCount(0, $list->destinations);
        self::assertTrue($list->replyToList);
        self::assertTrue($list->strictList);

        $list->addDestinations($bob);
        self::assertCount(1, $list->destinations);
        self::assertTrue($list->isMailingList());
    }

    public function testEmptyMailingListCanBeCreated(): void
    {
        $list = new Account('empty', $this->domain, AccountType::MailingList);

        self::assertTrue($list->isMailingList());
        self::assertNull($list->quota);
    }

    public function testAliasDestinationsCannotBeAddedOrRemoved(): void
    {
        $destination = new Destination('alice@example.com');
        $alias = new Account('info', $this->domain, AccountType::Alias, null, $destination);

        try {
            $alias->addDestinations(new Destination('bob@example.com'));
            self::fail('Adding a destination to an alias should fail.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $alias->removeDestination($destination);
    }

    public function testMailboxCannotHaveListSettings(): void
    {
        $mailbox = new Account('alice', $this->domain);

        $this->expectException(LogicException::class);
        $mailbox->setStrictList(true);
    }

    public function testQuotaOnlyForMailboxes(): void
    {
        $sendOnly = new Account('noreply', $this->domain, AccountType::SendOnly);

        $this->expectException(LogicException::class);
        $sendOnly->setQuota(100);
    }

    public function testQuotaIsClamped(): void
    {
        $mailbox = new Account('alice', $this->domain);

        self::assertSame(Account::MAX_QUOTA, $mailbox->setQuota(999999)->quota);
        self::assertSame(0, $mailbox->setQuota(-5)->quota);
    }

    public function testConversionsResetTypeSpecificState(): void
    {
        $list = new Account('team', $this->domain, AccountType::MailingList, null, new Destination('alice@example.com'));
        $list->setReplyToList(true);

        $list->makeMailbox(200);
        self::assertTrue($list->isMailbox());
        self::assertSame(200, $list->quota);
        self::assertCount(0, $list->destinations);
        self::assertFalse($list->replyToList);

        $list->makeAlias(new Destination('bob@example.com'));
        self::assertTrue($list->isAlias());
        self::assertNull($list->quota);

        $list->makeSendOnly();
        self::assertTrue($list->isSendOnly());
        self::assertCount(0, $list->destinations);
    }

    public function testCatchAllRules(): void
    {
        $alice = new Destination('alice@example.com');
        $catchAll = new Account(null, $this->domain, AccountType::MailingList, null, $alice);

        self::assertTrue($catchAll->isCatchAll());

        try {
            $catchAll->removeDestination($alice);
            self::fail('Removing the last destination of a catch-all should fail.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        new Account(null, $this->domain);
    }
}
