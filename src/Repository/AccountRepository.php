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

namespace App\Repository;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Destination;
use App\Entity\Domain;
use App\Exception\DomainNotFoundException;
use App\Exception\InvalidEmailException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The repository for user accounts.
 *
 * @extends EntityRepository<Account>
 * @implements UserProviderInterface<Account>
 */
final class AccountRepository extends EntityRepository implements PasswordUpgraderInterface, UserProviderInterface
{
    /**
     * The list of administrative account IDs.
     *
     * @var int[]
     */
    public readonly array $adminIds;

    /**
     * The repository for fetching domain entities.
     */
    private readonly DomainRepository $domainRepository;

    /**
     * Creates a new account.
     *
     * @param ?string $username The username part of the account's email address.
     * @param Domain|string $domain The domain part of the account's email address.
     * @param AccountType $type The type of the account.
     * @param ?string $hashedPassword The hashed password using algorithm bcrypt(10), or NULL to disable login.
     * @param Destination ...$destinations The destination (for aliases) or members (for mailing lists).
     *
     * @return Account The created account.
     *
     * @throws DomainNotFoundException if the given domain does not exist.
     */
    public function create(
        ?string $username,
        Domain|string $domain,
        AccountType $type = AccountType::Mailbox,
        ?string $hashedPassword = null,
        Destination ...$destinations
    ): Account {
        if (!$domain instanceof Domain) {
            $domain = $this->domainRepository->find($domain);

            if ($domain === null) {
                throw new DomainNotFoundException();
            }
        }

        return new Account($username, $domain, $type, $hashedPassword, ...$destinations);
    }

    /**
     * Creates a new account from an email address.
     *
     * @param string $email The email address (or "@domain" for a catch-all account) to create the account from.
     * @param AccountType $type The type of the account.
     * @param ?string $hashedPassword The hashed password using algorithm bcrypt(10), or NULL to disable login.
     * @param Destination ...$destinations The destination (for aliases) or members (for mailing lists).
     *
     * @return Account The created account.
     *
     * @throws InvalidEmailException if the given email address is not valid.
     * @throws DomainNotFoundException if the given domain does not exist.
     */
    public function createFromEmail(
        string $email,
        AccountType $type = AccountType::Mailbox,
        ?string $hashedPassword = null,
        Destination ...$destinations
    ): Account {
        [$username, $domain] = $this->splitEmail($email);

        if ($domain === null) {
            throw new InvalidEmailException();
        }

        return $this->create($username, $domain, $type, $hashedPassword, ...$destinations);
    }

    /**
     * Returns all mailing lists the given account is a list manager of.
     *
     * Note that this includes lists for which the account is no longer eligible as manager (e.g. because it is
     * disabled), so use ManagerPolicy::isListManager() for authorization decisions.
     *
     * @param Account $manager The manager.
     *
     * @return list<Account> The mailing lists, ordered by domain and username.
     */
    public function findListsByManager(Account $manager): array
    {
        return $this->getEntityResult(
            $this->createQueryBuilder('account')
                ->andWhere('account.type = :type')->setParameter('type', AccountType::MailingList)
                ->andWhere(':manager MEMBER OF account.managers')->setParameter('manager', $manager)
                ->addOrderBy('account.domain', 'ASC')
                ->addOrderBy('account.username', 'ASC')
        );
    }

    /**
     * Finds the account with the given email address (or "@domain" for a catch-all address).
     *
     * @param string $email The email address.
     *
     * @return ?Account The account, or NULL if it doesn't exist.
     */
    public function findOneByEmail(string $email): ?Account
    {
        [$username, $hostname] = $this->splitEmail($email);

        $domain = $hostname !== null ? $this->domainRepository->find($hostname) : null;

        return $domain !== null ? $this->findOneBy(['username' => $username, 'domain' => $domain]) : null;
    }

    /**
     * Loads the account for the given identifier (e.g. email).
     *
     * @param string $identifier The account identifier (e.g. email).
     *
     * @return Account The account corresponding to the given identifier.
     *
     * @throws UserNotFoundException if the account is not found.
     *
     * @see UserProviderInterface::loadUserByIdentifier()
     */
    #[\Override]
    public function loadUserByIdentifier(string $identifier): Account
    {
        $account = $this->findOneByEmail($identifier);

        if ($account === null || !$account->enabled) {
            $exception = new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $account->setAdmin($this->isAdminId($account->id));
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
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

        $this->getEntityManager()->persist($user->setPassword($newHashedPassword));
        $this->getEntityManager()->flush();
    }

    /**
     * Refreshes the account.
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

        $account = $this->findOneBy(['id' => $user->id, 'enabled' => true]);

        if ($account === null) {
            $exception = new UserNotFoundException(\sprintf('User with id "%d" not found.', $user->id));
            $exception->setUserIdentifier($user->getUserIdentifier());

            throw $exception;
        }

        return $account->setAdmin($this->isAdminId($account->id));
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
     * Whether the given account ID has administrative privileges.
     *
     * @param int $id The account ID.
     */
    private function isAdminId(int $id): bool
    {
        return \in_array($id, $this->adminIds, true);
    }

    /**
     * Splits an email address into username and domain.
     *
     * A bare domain name or "@domain" results in a NULL username (i.e. a catch-all address).
     *
     * @param string $email The email address.
     *
     * @return array{0: ?string, 1: ?string} The username and domain, each NULL if not present or invalid.
     */
    private function splitEmail(string $email): array
    {
        $email = trim($email);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            [$username, $domain] = explode('@', $email, 2) + [1 => ''];

            return [$username, $domain];
        }

        return [null, filter_var(trim($email, '@.'), FILTER_VALIDATE_DOMAIN, FILTER_NULL_ON_FAILURE)];
    }

    /**
     * @param ManagerRegistry $registry The object manager registry.
     * @param DomainRepository $domainRepository The repository for fetching domain entities.
     * @param int[] $adminIds The list of administrative account IDs.
     */
    public function __construct(ManagerRegistry $registry, DomainRepository $domainRepository, array $adminIds = [])
    {
        $this->adminIds = array_map('intval', array_filter($adminIds, 'is_numeric'));
        $this->domainRepository = $domainRepository;

        parent::__construct($registry, Account::class);
    }
}
