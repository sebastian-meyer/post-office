<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Account;
use App\Tests\TestData;
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

    private static function user(TestData $data, string $name): Account
    {
        $user = $data->{$name};
        \assert($user instanceof Account);

        return $user;
    }
}
