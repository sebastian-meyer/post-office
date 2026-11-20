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

namespace App\Twig;

use App\Entity\Account;
use App\Security\AdminAccounts;
use App\Service\ManagementScope;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig functions for the management area.
 */
final class ManagementExtension extends AbstractExtension
{
    /**
     * Registers the Twig functions.
     *
     * @return list<TwigFunction> The functions.
     *
     * @see AbstractExtension::getFunctions()
     */
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_admin_account', $this->isAdminAccount(...)),
            new TwigFunction('manages_anything', $this->managesAnything(...))
        ];
    }

    /**
     * Whether the account is an administrator.
     *
     * Wraps AdminAccounts::isAdmin() so that getFunctions() does not access any property. The Symfony plugin for
     * Psalm instantiates Twig extensions without calling the constructor to analyze the templates.
     *
     * @param Account $account The account.
     */
    public function isAdminAccount(Account $account): bool
    {
        return $this->adminAccounts->isAdmin($account);
    }

    /**
     * Whether the current user can manage any domain or mailing list (e.g. to show the management link).
     */
    public function managesAnything(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof Account && $this->managementScope->managesAnything($user);
    }

    /**
     * @param Security $security The security helper (to get the current user).
     * @param ManagementScope $managementScope The service determining what a user can manage.
     * @param AdminAccounts $adminAccounts The service knowing the administrator accounts.
     */
    public function __construct(
        private readonly Security $security,
        private readonly ManagementScope $managementScope,
        private readonly AdminAccounts $adminAccounts
    ) {
    }
}
