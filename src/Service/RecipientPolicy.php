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

/**
 * Knows which accounts the mail server accepts emails for (see the Postfix configuration in mailserver/postfix/).
 *
 * The mail server rejects recipients that are disabled, send-only addresses or mailing lists without members, and
 * emails to "members only" lists from senders who aren't members. Catch-all accounts have no address of their own.
 * A rejected recipient aborts the delivery of an email to several recipients (see BulkMailer), so such accounts must
 * be left out beforehand.
 */
final class RecipientPolicy
{
    /**
     * Whether the mail server accepts emails from the sender to the account's address.
     *
     * @param Account $account The recipient.
     * @param Account $sender The sender (the current user).
     */
    public function canReceiveEmails(Account $account, Account $sender): bool
    {
        return $account->enabled
            && $account->username !== null
            && !$account->isSendOnly()
            && (!$account->isMailingList() || $this->acceptsListPost($account, $sender));
    }

    /**
     * Whether a mailing list accepts emails from the sender.
     *
     * @param Account $list The mailing list.
     * @param Account $sender The sender.
     */
    private function acceptsListPost(Account $list, Account $sender): bool
    {
        if ($list->destinations->isEmpty()) {
            return false;
        }

        if (!$list->strictList) {
            return true;
        }

        foreach ($list->destinations as $member) {
            if (strcasecmp((string) $member, $sender->getUserIdentifier()) === 0) {
                return true;
            }
        }

        return false;
    }
}
