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

namespace App\Form\Model;

use App\Entity\Account;

/**
 * Form data for creating a mailing list or editing its settings.
 *
 * Members and list managers are managed separately (see MemberData and ManagerData).
 */
final class MailingListData extends AccountData
{
    /**
     * Whether responses should be sent back to the mailing list (Reply-To header).
     */
    public bool $replyToList = false;

    /**
     * Whether only list members should be allowed to post to the list.
     */
    public bool $strictList = false;

    /**
     * Creates the form data from an existing mailing list.
     *
     * @param Account $account The mailing list.
     */
    public static function fromAccount(Account $account): self
    {
        $data = new self();
        $data->copyFrom($account);
        $data->replyToList = $account->replyToList;
        $data->strictList = $account->strictList;

        return $data;
    }
}
