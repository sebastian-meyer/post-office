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
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Knows which accounts are administrators (configured by their IDs in the ADMIN_IDS environment variable).
 */
final class AdminAccounts
{
    /**
     * The IDs of the administrator accounts.
     *
     * @var list<int>
     */
    private readonly array $ids;

    /**
     * Whether the account is an administrator.
     *
     * @param Account $account The account.
     */
    public function isAdmin(Account $account): bool
    {
        // New accounts don't have an ID yet, so they can't be administrators.
        if (!(new ReflectionProperty(Account::class, 'id'))->isInitialized($account)) {
            return false;
        }

        return \in_array($account->id, $this->ids, true);
    }

    /**
     * @param array<array-key, mixed> $adminIds The IDs of the administrator accounts (non-numeric values are ignored).
     */
    public function __construct(
        #[Autowire(param: 'app.admin_ids')]
        array $adminIds = []
    ) {
        $this->ids = array_values(array_map('intval', array_filter($adminIds, 'is_numeric')));
    }
}
