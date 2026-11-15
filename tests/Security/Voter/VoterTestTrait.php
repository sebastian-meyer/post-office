<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Account;
use App\Security\AdminAccounts;
use App\Tests\TestData;
use ReflectionProperty;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

trait VoterTestTrait
{
    private static function decisionManager(): AccessDecisionManager
    {
        return new AccessDecisionManager([new RoleVoter()]);
    }

    private static function token(Account $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    /**
     * @param Voter<string, mixed> $voter
     */
    private static function isGranted(Voter $voter, Account $user, string $attribute, mixed $subject): bool
    {
        return $voter->vote(self::token($user), $subject, [$attribute]) === VoterInterface::ACCESS_GRANTED;
    }

    /**
     * Returns the administrator accounts of the test data (admin@example.com with ID 1, like in the database tests).
     *
     * The test data is not persisted in unit tests, so the ID is set directly.
     */
    private static function adminAccounts(TestData $data): AdminAccounts
    {
        (new ReflectionProperty(Account::class, 'id'))->setValue($data->admin, 1);

        return new AdminAccounts(['1']);
    }

    private static function user(TestData $data, string $name): Account
    {
        $user = $data->{$name};
        \assert($user instanceof Account);

        return $user;
    }
}
