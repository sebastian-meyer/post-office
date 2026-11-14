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

namespace App\Entity;

/**
 * The type of account.
 */
enum AccountType: string
{
    /**
     * A mailbox that stores incoming emails and allows login.
     *
     * Mailboxes have a quota and no destinations.
     */
    case Mailbox = 'mailbox';

    /**
     * An alias that forwards incoming emails to exactly one destination.
     */
    case Alias = 'alias';

    /**
     * A mailing list that forwards incoming emails to any number of destinations (its members).
     */
    case MailingList = 'list';

    /**
     * An address that is only allowed to send emails.
     *
     * Send-only accounts have neither a quota nor destinations.
     */
    case SendOnly = 'sendonly';

    /**
     * Whether an account of this type may have the given number of destinations.
     *
     * @param int $count The number of destinations.
     */
    public function acceptsDestinationCount(int $count): bool
    {
        return match ($this) {
            self::Mailbox, self::SendOnly => $count === 0,
            self::Alias => $count === 1,
            self::MailingList => $count >= 0
        };
    }
}
