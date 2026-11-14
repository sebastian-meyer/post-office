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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form data for creating or editing a mailbox.
 */
final class MailboxData extends AccountData
{
    /**
     * The quota in megabytes (0 means unlimited).
     */
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: Account::MAX_QUOTA)]
    public ?int $quota = Account::DEFAULT_QUOTA;

    /**
     * The new password. Required when creating a mailbox, optional when editing (empty means unchanged).
     */
    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\NotCompromisedPassword]
    #[Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_STRONG)]
    public ?string $plainPassword = null;

    /**
     * Creates the form data from an existing mailbox.
     *
     * @param Account $account The mailbox.
     */
    public static function fromAccount(Account $account): self
    {
        $data = new self();
        $data->copyFrom($account);
        $data->quota = $account->quota;

        return $data;
    }
}
