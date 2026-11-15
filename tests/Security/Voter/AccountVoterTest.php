<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Security\ManagerPolicy;
use App\Security\Voter\AccountVoter;
use App\Tests\TestData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountVoterTest extends TestCase
{
    use VoterTestTrait;

    private const array ATTRIBUTES = [
        AccountVoter::MANAGE,
        AccountVoter::DELETE,
        AccountVoter::LIST_MANAGE,
        AccountVoter::LIST_MANAGE_MANAGERS,
        AccountVoter::LIST_LEAVE
    ];

    /**
     * Expected permissions in the order of self::ATTRIBUTES (MANAGE, DELETE, LIST_MANAGE, LIST_MANAGE_MANAGERS, LIST_LEAVE).
     *
     * @return iterable<string, array{string, string, list<bool>}>
     */
    public static function permissions(): iterable
    {
        // Mailing list team@example.com (members: alice, bob, dave, external; list manager: bob)
        yield 'admin on list' => ['admin', 'team', [true, true, true, true, false]];
        yield 'domain manager (and member) on list' => ['alice', 'team', [true, true, true, true, true]];
        yield 'list manager (and member) on list' => ['bob', 'team', [false, false, true, false, true]];
        yield 'member from other domain on list' => ['dave', 'team', [false, false, false, false, true]];
        yield 'manager of other domain on list' => ['carol', 'team', [false, false, false, false, false]];

        // Alias info@example.com: list attributes never apply
        yield 'admin on alias' => ['admin', 'info', [true, true, false, false, false]];
        yield 'domain manager on alias' => ['alice', 'info', [true, true, false, false, false]];
        yield 'list manager on alias' => ['bob', 'info', [false, false, false, false, false]];

        // Mailboxes: nobody can delete their own account
        yield 'domain manager on own mailbox' => ['alice', 'alice', [true, false, false, false, false]];
        yield 'domain manager on other mailbox' => ['alice', 'bob', [true, true, false, false, false]];
        yield 'admin on own mailbox' => ['admin', 'admin', [true, false, false, false, false]];

        // Administrator accounts can only be edited by administrators, and never be deleted.
        yield 'domain manager on admin mailbox' => ['alice', 'admin', [false, false, false, false, false]];
        yield 'regular user on own mailbox' => ['dave', 'dave', [false, false, false, false, false]];
        yield 'manager of other domain on mailbox' => ['carol', 'bob', [false, false, false, false, false]];
    }

    /**
     * @param list<bool> $expected
     */
    #[DataProvider('permissions')]
    public function testPermissions(string $userName, string $subjectName, array $expected): void
    {
        $data = new TestData();
        $voter = new AccountVoter(self::decisionManager(), new ManagerPolicy(), self::adminAccounts($data));
        $user = self::user($data, $userName);
        $subject = self::user($data, $subjectName);

        $actual = array_map(
            static fn (string $attribute): bool => self::isGranted($voter, $user, $attribute, $subject),
            self::ATTRIBUTES
        );

        self::assertSame(array_combine(self::ATTRIBUTES, $expected), array_combine(self::ATTRIBUTES, $actual));
    }

    public function testDisabledListManagerLosesPermissions(): void
    {
        $data = new TestData();
        $data->bob->disable();

        self::assertFalse(self::isGranted(new AccountVoter(self::decisionManager(), new ManagerPolicy(), self::adminAccounts($data)), $data->bob, AccountVoter::LIST_MANAGE, $data->team));
    }

    public function testMembershipIsCaseInsensitive(): void
    {
        $data = new TestData();
        $policy = new ManagerPolicy();

        self::assertTrue($policy->isListMember($data->dave, $data->team));
        self::assertFalse($policy->isListMember($data->carol, $data->team));
    }
}
