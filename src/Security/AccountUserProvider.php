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

namespace App\Security;

use App\Entity\Account;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads accounts for logging in with the email address and password of an enabled account.
 *
 * @implements UserProviderInterface<Account>
 */
final class AccountUserProvider implements PasswordUpgraderInterface, UserProviderInterface
{
    /**
     * Loads the account for the given identifier (e.g. email).
     *
     * @param string $identifier The account identifier (e.g. email).
     *
     * @return Account The account corresponding to the given identifier.
     *
     * @throws UserNotFoundException if the account is not found or disabled.
     *
     * @see UserProviderInterface::loadUserByIdentifier()
     */
    #[\Override]
    public function loadUserByIdentifier(string $identifier): Account
    {
        $account = $this->accountRepository->findOneByEmail($identifier);

        if ($account === null || !$account->enabled) {
            $exception = new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $account->setAdmin($this->adminAccounts->isAdmin($account));
    }

    /**
     * Refreshes the account (on every request of a logged-in user).
     *
     * @param UserInterface $user The account to refresh.
     *
     * @return Account The refreshed account.
     *
     * @throws UnsupportedUserException if the account type is not supported.
     * @throws UserNotFoundException if the account is not found or has been disabled.
     *
     * @see UserProviderInterface::refreshUser()
     */
    #[\Override]
    public function refreshUser(UserInterface $user): Account
    {
        if (!$user instanceof Account) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        $account = $this->accountRepository->findOneBy(['id' => $user->id, 'enabled' => true]);

        if ($account === null) {
            $exception = new UserNotFoundException(\sprintf('User with id "%d" not found.', $user->id));
            $exception->setUserIdentifier($user->getUserIdentifier());

            throw $exception;
        }

        return $account->setAdmin($this->adminAccounts->isAdmin($account));
    }

    /**
     * Whether this provider supports the given user class.
     *
     * @param string $class The account/user class.
     *
     * @see UserProviderInterface::supportsClass()
     */
    #[\Override]
    public function supportsClass(string $class): bool
    {
        return is_a($class, Account::class, true);
    }

    /**
     * Saves a new password hash, e.g. to upgrade (rehash) the user's password automatically over time.
     *
     * @param PasswordAuthenticatedUserInterface $user The user whose password should be upgraded.
     * @param string $newHashedPassword The new hashed password using algorithm bcrypt(10).
     *
     * @throws UnsupportedUserException if the account is not supported.
     *
     * @see PasswordUpgraderInterface::upgradePassword()
     */
    #[\Override]
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Account) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $this->entityManager->persist($user->setPassword($newHashedPassword));
        $this->entityManager->flush();
    }

    /**
     * @param AccountRepository $accountRepository The repository for accounts.
     * @param AdminAccounts $adminAccounts The service knowing the administrator accounts.
     * @param EntityManagerInterface $entityManager The entity manager (for saving new password hashes).
     */
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly AdminAccounts $adminAccounts,
        private readonly EntityManagerInterface $entityManager
    ) {
    }
}
