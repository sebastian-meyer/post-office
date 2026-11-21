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
 * Decides which email addresses belong to an account, like the mail server does.
 *
 * Postfix and Dovecot deliver addresses with an extension ("bob+news@example.com") to the account without it
 * ("bob@example.com", see recipient_delimiter in mailserver/), and compare addresses case-insensitively.
 */
final class AccountAddresses
{
    /**
     * The separator of address extensions (recipient_delimiter in Postfix and Dovecot).
     */
    public const string DELIMITER = '+';

    /**
     * Whether the email address belongs to the account (the account's address, with or without extension).
     *
     * Catch-all accounts have no address of their own, so no address belongs to them.
     *
     * @param string $address The email address.
     * @param Account $account The account.
     */
    public static function belongsTo(string $address, Account $account): bool
    {
        if ($account->username === null) {
            return false;
        }

        [$localPart, $domain] = explode('@', strtolower(trim($address)), 2) + [1 => ''];

        return $domain === strtolower($account->domain->domain)
            && explode(self::DELIMITER, $localPart, 2)[0] === strtolower($account->username);
    }

    /**
     * Returns a LIKE pattern for the addresses with extension of the account (e.g. "bob+%@example.com").
     *
     * The wildcards in the username and domain are escaped with "!".
     *
     * @param Account $account The account (not a catch-all account).
     */
    public static function extensionPattern(Account $account): string
    {
        $escape = static fn (string $value): string => strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);

        return $escape((string) $account->username) . self::DELIMITER . '%@' . $escape($account->domain->domain);
    }
}
