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
use App\Entity\Domain;
use App\Validator\AvailableAddress;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Form data shared by all account types.
 *
 * Username and domain can only be set when creating an account (validation group "create"), since the mail storage
 * location depends on them.
 */
#[AvailableAddress(groups: ['create'])]
abstract class AccountData
{
    /**
     * The username part of the email address, or NULL for a catch-all address.
     */
    #[Assert\NotBlank(allowNull: true, groups: ['create'])]
    #[Assert\Length(max: 64, groups: ['create'])]
    #[Assert\Regex(pattern: "/^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+$/", message: 'invalidUsername', groups: ['create'])]
    public ?string $username = null;

    /**
     * The domain part of the email address.
     */
    #[Assert\NotNull(groups: ['create'])]
    public ?Domain $domain = null;

    /**
     * The label (e.g. the owner's name).
     */
    #[Assert\Length(max: 255)]
    #[Assert\NoSuspiciousCharacters]
    public ?string $label = null;

    /**
     * Whether the account is enabled.
     */
    public bool $enabled = true;

    /**
     * Requires a username for account types that cannot be catch-all addresses.
     *
     * @param ExecutionContextInterface $context The validation context.
     */
    #[Assert\Callback(groups: ['create'])]
    public function validateUsername(ExecutionContextInterface $context): void
    {
        if ($this->username === null && !$this->allowsCatchAll()) {
            $context->buildViolation('usernameRequired')->atPath('username')->addViolation();
        }
    }

    /**
     * Whether the account can be a catch-all address (i.e. have no username).
     *
     * Only aliases can, because catch-all addresses need a destination from the start.
     */
    protected function allowsCatchAll(): bool
    {
        return false;
    }

    /**
     * Copies the common fields from an existing account.
     *
     * @param Account $account The account.
     */
    protected function copyFrom(Account $account): void
    {
        $this->username = $account->username;
        $this->domain = $account->domain;
        $this->label = $account->label;
        $this->enabled = $account->enabled;
    }
}
