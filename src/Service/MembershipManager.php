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

namespace App\Service;

use App\Entity\Account;
use App\Entity\Destination;
use App\Entity\Domain;
use App\Repository\AccountRepository;
use App\Repository\DestinationRepository;
use App\Repository\DomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * Manages mailing list members and manager roles (domain managers and list managers).
 *
 * Authorization is not checked here, but by the controllers (see App\Security\Voter).
 */
final class MembershipManager
{
    /**
     * Appoints a domain manager.
     *
     * @param Domain $domain The domain.
     * @param string $email The email address of the new manager.
     *
     * @throws LogicException if the account doesn't exist or cannot be a manager of the domain.
     */
    public function addDomainManager(Domain $domain, string $email): void
    {
        $domain->addManager($this->findAccount($email));
        $this->entityManager->flush();
    }

    /**
     * Appoints a list manager.
     *
     * @param Account $list The mailing list.
     * @param string $email The email address of the new manager.
     *
     * @throws LogicException if the account doesn't exist or cannot be a manager of the list.
     */
    public function addListManager(Account $list, string $email): void
    {
        $list->addManager($this->findAccount($email));
        $this->entityManager->flush();
    }

    /**
     * Adds a member to a mailing list.
     *
     * Adding an address that is already a member (ignoring case) has no effect.
     *
     * @param Account $list The mailing list.
     * @param string $email The member's email address.
     *
     * @throws LogicException if the account is not a mailing list.
     */
    public function addMember(Account $list, string $email): void
    {
        if ($this->findMember($list, $email) !== null) {
            return;
        }

        $list->addDestinations($this->destinationRepo->getEntity($email));
        $this->entityManager->flush();
    }

    /**
     * Removes a domain manager.
     *
     * @param Domain $domain The domain.
     * @param Account $manager The manager to remove.
     */
    public function removeDomainManager(Domain $domain, Account $manager): void
    {
        $domain->removeManager($manager);
        $this->entityManager->flush();
    }

    /**
     * Removes a list manager.
     *
     * @param Account $list The mailing list.
     * @param Account $manager The manager to remove.
     */
    public function removeListManager(Account $list, Account $manager): void
    {
        $list->removeManager($manager);
        $this->entityManager->flush();
    }

    /**
     * Removes a member from a mailing list (e.g. when a user leaves a list).
     *
     * The email address is matched case-insensitively. Removing a non-member has no effect.
     *
     * @param Account $list The mailing list.
     * @param string $email The member's email address.
     *
     * @throws LogicException if the account is not a mailing list, or the last member of a catch-all list is removed.
     */
    public function removeMember(Account $list, string $email): void
    {
        $destination = $this->findMember($list, $email);

        if ($destination !== null) {
            $list->removeDestination($destination);
            $this->entityManager->flush();
        }
    }

    /**
     * Removes the account's email address from all mailing lists (e.g. before the account is deleted).
     *
     * Otherwise, the lists would keep forwarding to the address, and whoever gets the address next would receive
     * the list's emails. The address cannot be removed from catch-all lists of which it is the last member; these
     * lists are returned unchanged, so they can be deleted as well (see AccountManager::delete()).
     *
     * This does not flush the changes.
     *
     * @param Account $account The (former) member.
     *
     * @return list<Account> The catch-all lists the address could not be removed from.
     */
    public function removeFromAllLists(Account $account): array
    {
        if ($account->isCatchAll()) {
            return [];
        }

        $remaining = [];

        foreach ($this->accountRepository->findListsByMember($account) as $list) {
            $destination = $this->findMember($list, $account->getUserIdentifier());

            if ($destination === null) {
                continue;
            }

            try {
                $list->removeDestination($destination);
            } catch (LogicException) {
                $remaining[] = $list;
            }
        }

        return $remaining;
    }

    /**
     * Removes the account from all domain and list manager roles (before it is deleted).
     *
     * This does not flush the changes.
     *
     * @param Account $account The (former) manager.
     */
    public function revokeManagerRoles(Account $account): void
    {
        foreach ($this->domainRepository->findByManager($account) as $domain) {
            $domain->removeManager($account);
        }

        foreach ($this->accountRepository->findListsByManager($account) as $list) {
            $list->removeManager($account);
        }
    }

    /**
     * Finds an account by email address.
     *
     * @param string $email The email address.
     *
     * @throws LogicException if no account exists.
     */
    private function findAccount(string $email): Account
    {
        return $this->accountRepository->findOneByEmail($email)
            ?? throw new LogicException(\sprintf('No account found for "%s".', $email));
    }

    /**
     * Finds the list's destination matching the email address (ignoring case).
     *
     * @param Account $list The mailing list.
     * @param string $email The email address.
     */
    private function findMember(Account $list, string $email): ?Destination
    {
        foreach ($list->destinations as $destination) {
            if (strcasecmp((string) $destination, trim($email)) === 0) {
                return $destination;
            }
        }

        return null;
    }

    /**
     * @param EntityManagerInterface $entityManager The entity manager.
     * @param AccountRepository $accountRepository The repository for accounts.
     * @param DomainRepository $domainRepository The repository for domains.
     * @param DestinationRepository $destinationRepo The repository for destinations.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountRepository $accountRepository,
        private readonly DomainRepository $domainRepository,
        private readonly DestinationRepository $destinationRepo
    ) {
    }
}
