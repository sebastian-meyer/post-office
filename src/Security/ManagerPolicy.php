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
use App\Entity\Destination;
use App\Service\AccountAddresses;

/**
 * Rules for list managers and list members.
 *
 * Domain managers are checked by Domain::isManagedBy(), and administrators by their role.
 */
final class ManagerPolicy
{
    /**
     * Whether the user is a list manager of the mailing list.
     *
     * This only checks for list managers, not for domain managers or administrators.
     *
     * @param Account $user The (potential) manager.
     * @param Account $list The mailing list.
     */
    public function isListManager(Account $user, Account $list): bool
    {
        return $list->isMailingList()
            && $user->canBeManager()
            && $list->managers->contains($user);
    }

    /**
     * Whether the user is a member of the mailing list.
     *
     * The user's email address is compared case-insensitively with the list's destinations.
     *
     * @param Account $user The (potential) member.
     * @param Account $list The mailing list.
     */
    public function isListMember(Account $user, Account $list): bool
    {
        if (!$list->isMailingList()) {
            return false;
        }

        return $list->destinations->exists(
            static fn (int|string $key, Destination $destination): bool => AccountAddresses::belongsTo((string) $destination, $user)
        );
    }
}
