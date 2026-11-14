<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Security\Voter\DomainVoter;
use App\Tests\TestData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class DomainVoterTest extends TestCase
{
    use VoterTestTrait;

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function permissions(): iterable
    {
        // user => [user, MANAGE example.com, MANAGE_MANAGERS example.com]
        yield 'admin' => ['admin', true, true];
        yield 'domain manager' => ['alice', true, false];
        yield 'manager of other domain' => ['carol', false, false];
        yield 'list manager' => ['bob', false, false];
        yield 'regular user' => ['dave', false, false];
    }

    #[DataProvider('permissions')]
    public function testPermissions(string $userName, bool $manage, bool $manageManagers): void
    {
        $data = new TestData();
        $voter = new DomainVoter(self::decisionManager());
        $user = self::user($data, $userName);

        self::assertSame($manage, self::isGranted($voter, $user, DomainVoter::MANAGE, $data->exampleCom));
        self::assertSame($manageManagers, self::isGranted($voter, $user, DomainVoter::MANAGE_MANAGERS, $data->exampleCom));
    }

    public function testDisabledManagerLosesPermissions(): void
    {
        $data = new TestData();
        $data->alice->disable();

        self::assertFalse(self::isGranted(new DomainVoter(self::decisionManager()), $data->alice, DomainVoter::MANAGE, $data->exampleCom));
    }

    public function testManagerConvertedToAliasLosesPermissions(): void
    {
        $data = new TestData();
        $data->alice->makeAlias($data->team->destinations->first() ?: throw new \LogicException());

        self::assertFalse(self::isGranted(new DomainVoter(self::decisionManager()), $data->alice, DomainVoter::MANAGE, $data->exampleCom));
    }

    public function testAbstainsOnOtherSubjects(): void
    {
        $data = new TestData();
        $voter = new DomainVoter(self::decisionManager());

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(self::token($data->admin), $data->alice, [DomainVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(self::token($data->admin), $data->exampleCom, ['ROLE_ADMIN']));
    }
}
