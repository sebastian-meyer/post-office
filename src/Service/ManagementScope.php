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
use App\Entity\Domain;
use App\Repository\AccountRepository;
use App\Repository\DomainRepository;
use App\Security\ManagerPolicy;

/**
 * Determines which domains and mailing lists a user can manage.
 *
 * This is used for navigation and overview pages. Authorization decisions are made by the security voters, which
 * apply the same rules (see App\Security\Voter).
 */
final class ManagementScope
{
    /**
     * Returns the domains the user can manage.
     *
     * Administrators can manage all domains, domain managers only their own.
     *
     * @param Account $user The current user.
     *
     * @return list<Domain> The domains, ordered by name.
     */
    public function getManagedDomains(Account $user): array
    {
        if ($this->isAdmin($user)) {
            return $this->domainRepository->findBy([], ['domain' => 'ASC']);
        }

        return array_values(array_filter(
            $this->domainRepository->findByManager($user),
            static fn (Domain $domain): bool => $domain->isManagedBy($user)
        ));
    }

    /**
     * Returns the mailing lists the user is explicitly appointed to as list manager.
     *
     * Lists the user can manage as administrator or domain manager are not included, since those are reachable
     * through the domains (see getManagedDomains()).
     *
     * @param Account $user The current user.
     *
     * @return list<Account> The mailing lists, ordered by domain and username.
     */
    public function getManagedLists(Account $user): array
    {
        return array_values(array_filter(
            $this->accountRepository->findListsByManager($user),
            fn (Account $list): bool => $this->policy->isListManager($user, $list)
        ));
    }

    /**
     * Whether the user can manage anything at all (e.g. to show management links in the navigation).
     *
     * @param Account $user The current user.
     */
    public function managesAnything(Account $user): bool
    {
        return $this->isAdmin($user) || $this->getManagedDomains($user) !== [] || $this->getManagedLists($user) !== [];
    }

    /**
     * Whether the user is an administrator.
     *
     * @param Account $user The user.
     */
    private function isAdmin(Account $user): bool
    {
        return \in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    /**
     * @param AccountRepository $accountRepository The repository for accounts.
     * @param DomainRepository $domainRepository The repository for domains.
     * @param ManagerPolicy $policy The rules for list managers and members.
     */
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly DomainRepository $domainRepository,
        private readonly ManagerPolicy $policy
    ) {
    }
}
