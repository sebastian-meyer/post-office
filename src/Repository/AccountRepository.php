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
use App\Entity\Domain;
use App\Service\AccountAddresses;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * The repository for user accounts.
 *
 * Logging in with accounts is handled by App\Security\AccountUserProvider.
 *
 * @extends EntityRepository<Account>
 */
final class AccountRepository extends EntityRepository
{
    /**
     * Returns the enabled mailboxes of the domain as suggestions for appointing managers.
     *
     * Enabled mailboxes of other domains can be managers as well, but they are not suggested to avoid disclosing
     * the accounts of other domains to domain managers.
     *
     * @param Domain $domain The domain.
     *
     * @return list<Account> The accounts, ordered by username.
     *
     * @see Account::canBeManager()
     */
    public function findManagerCandidates(Domain $domain): array
    {
        return $this->getEntityResult(
            $this->createQueryBuilder('account')
                ->andWhere('account.domain = :domain')->setParameter('domain', $domain->domain, Types::STRING)
                ->andWhere('account.type = :type')->setParameter('type', AccountType::Mailbox)
                ->andWhere('account.enabled = true')
                ->addOrderBy('account.username', SortDirection::Ascending)
        );
    }

    /**
     * Returns all aliases (including catch-all aliases) forwarding to the given account.
     *
     * This includes aliases forwarding to an address with extension (see AccountAddresses).
     *
     * @param Account $destination The destination.
     *
     * @return list<Account> The aliases, ordered by domain and username.
     */
    public function findAliasesByDestination(Account $destination): array
    {
        return $this->findByDestinationOf($destination, AccountType::Alias);
    }

    /**
     * Returns all mailing lists the given account is a member of.
     *
     * This includes lists with an address with extension as member (see AccountAddresses).
     *
     * @param Account $member The member.
     *
     * @return list<Account> The mailing lists, ordered by domain and username.
     */
    public function findListsByMember(Account $member): array
    {
        return $this->findByDestinationOf($member, AccountType::MailingList);
    }

    /**
     * Returns all accounts of the given type with a destination belonging to the account.
     *
     * Destinations are identified by their email address, which the database collation compares case-insensitively.
     *
     * @param Account $account The account the destinations belong to.
     * @param AccountType $type The type of the accounts to return.
     *
     * @return list<Account> The accounts, ordered by domain and username.
     */
    private function findByDestinationOf(Account $account, AccountType $type): array
    {
        if ($account->isCatchAll()) {
            return [];
        }

        return $this->getEntityResult(
            $this->createQueryBuilder('account')
                ->distinct()
                ->innerJoin('account.destinations', 'destination')
                ->andWhere('account.type = :type')->setParameter('type', $type->value, Types::STRING)
                ->andWhere("destination.destination = :email OR destination.destination LIKE :extension ESCAPE '!'")
                ->setParameter('email', $account->getUserIdentifier())
                ->setParameter('extension', AccountAddresses::extensionPattern($account))
                ->addOrderBy('account.domain', SortDirection::Ascending)
                ->addOrderBy('account.username', SortDirection::Ascending)
        );
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
                ->andWhere(':manager MEMBER OF account.managers')->setParameter('manager', $manager->id, Types::INTEGER)
                ->addOrderBy('account.domain', SortDirection::Ascending)
                ->addOrderBy('account.username', SortDirection::Ascending)
        );
    }

    /**
     * Finds the accounts with the given email addresses (e.g. to check which list members are local accounts).
     *
     * @param list<string> $emails The email addresses.
     *
     * @return list<Account> The accounts, in no particular order.
     */
    public function findByEmails(array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        return $this->getEntityResult(
            $this->createQueryBuilder('account')
                ->andWhere("CONCAT(account.username, '@', IDENTITY(account.domain)) IN (:emails)")->setParameter('emails', $emails)
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
     */
    public function __construct(
        ManagerRegistry $registry,
        private readonly DomainRepository $domainRepository
    ) {
        parent::__construct($registry, Account::class);
    }
}
