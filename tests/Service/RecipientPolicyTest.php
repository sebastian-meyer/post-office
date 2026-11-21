<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Destination;
use App\Entity\Domain;
use App\Service\RecipientPolicy;
use PHPUnit\Framework\TestCase;

final class RecipientPolicyTest extends TestCase
{
    public function testCanReceiveEmails(): void
    {
        $policy = new RecipientPolicy();
        $domain = new Domain('example.com');
        $member = new Destination('alice@example.com');
        $sender = new Account('bob', $domain);

        self::assertTrue($policy->canReceiveEmails(new Account('alice', $domain), $sender));
        self::assertTrue($policy->canReceiveEmails(new Account('info', $domain, AccountType::Alias, null, $member), $sender));
        self::assertTrue($policy->canReceiveEmails(new Account('team', $domain, AccountType::MailingList, null, $member), $sender));
        self::assertFalse($policy->canReceiveEmails(new Account('empty', $domain, AccountType::MailingList), $sender));
        self::assertFalse($policy->canReceiveEmails(new Account('noreply', $domain, AccountType::SendOnly), $sender));
        self::assertFalse($policy->canReceiveEmails(new Account(null, $domain, AccountType::Alias, null, $member), $sender));
        self::assertFalse($policy->canReceiveEmails((new Account('bob', $domain))->disable(), $sender));
    }

    public function testMembersOnlyListAcceptsMembersOnly(): void
    {
        $policy = new RecipientPolicy();
        $domain = new Domain('example.com');
        $list = new Account('team', $domain, AccountType::MailingList, null, new Destination('Alice@example.com'));
        $list->setListOptions(false, true);

        self::assertTrue($policy->canReceiveEmails($list, new Account('alice', $domain)));
        self::assertFalse($policy->canReceiveEmails($list, new Account('bob', $domain)));
    }
}
