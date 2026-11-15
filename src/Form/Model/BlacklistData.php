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

use App\Entity\Blacklist;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Form data for creating or editing a blacklist entry.
 *
 * The address can only be set when creating an entry (validation group "create").
 */
final class BlacklistData
{
    /**
     * The blacklisted email address, or a domain (which includes its subdomains).
     */
    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Length(max: 254, groups: ['create'])]
    public ?string $address = null;

    /**
     * The action for emails from blacklisted senders (DISCARD or REJECT).
     */
    #[Assert\NotBlank]
    #[Assert\Choice(choices: Blacklist::ACTIONS)]
    public ?string $action = 'REJECT';

    /**
     * Whether the entry is enabled.
     */
    public bool $enabled = true;

    /**
     * Creates the form data from an existing entry.
     *
     * @param Blacklist $blacklist The entry.
     */
    public static function fromBlacklist(Blacklist $blacklist): self
    {
        $data = new self();
        $data->action = $blacklist->action;
        $data->enabled = $blacklist->enabled;

        return $data;
    }

    /**
     * Checks that the address is an email address or a domain.
     *
     * @param ExecutionContextInterface $context The validation context.
     */
    #[Assert\Callback(groups: ['create'])]
    public function validateAddress(ExecutionContextInterface $context): void
    {
        if ($this->address === null || $this->address === '') {
            return;
        }

        $address = trim($this->address);
        $isEmail = filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
        $isDomain = filter_var(trim($address, '@.'), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false && str_contains($address, '.');

        if (!$isEmail && !$isDomain) {
            $context->buildViolation('invalidEmailOrDomain')->atPath('address')->addViolation();
        }
    }
}
