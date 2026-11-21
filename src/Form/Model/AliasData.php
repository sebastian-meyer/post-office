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
use App\Validator\MailAddress;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form data for creating or editing an alias.
 */
final class AliasData extends AccountData
{
    /**
     * The destination email address.
     */
    #[Assert\NotBlank]
    #[Assert\Email]
    #[MailAddress]
    #[Assert\Length(max: 254)]
    public ?string $destination = null;

    /**
     * Aliases can be catch-all addresses.
     */
    #[\Override]
    protected function allowsCatchAll(): bool
    {
        return true;
    }

    /**
     * Creates the form data from an existing alias.
     *
     * @param Account $account The alias.
     */
    public static function fromAccount(Account $account): self
    {
        $data = new self();
        $data->copyFrom($account);
        $destination = $account->destinations->first();
        $data->destination = $destination !== false ? (string) $destination : null;

        return $data;
    }
}
