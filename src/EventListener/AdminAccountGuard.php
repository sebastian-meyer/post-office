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

namespace App\EventListener;

use App\Entity\Account;
use App\Security\AdminAccounts;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use LogicException;

/**
 * Prevents administrator accounts from being deleted, so the system can't end up without an administrator.
 *
 * This is the last line of defence; the security voters already deny deleting administrator accounts.
 */
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: Account::class)]
final class AdminAccountGuard
{
    /**
     * Throws if an administrator account is about to be deleted.
     *
     * @param Account $account The account to delete.
     *
     * @throws LogicException if the account is an administrator.
     */
    public function preRemove(Account $account): void
    {
        if ($this->adminAccounts->isAdmin($account)) {
            throw new LogicException(\sprintf('The administrator account "%s" cannot be deleted.', $account));
        }
    }

    /**
     * @param AdminAccounts $adminAccounts The service knowing the administrator accounts.
     */
    public function __construct(
        private readonly AdminAccounts $adminAccounts
    ) {
    }
}
