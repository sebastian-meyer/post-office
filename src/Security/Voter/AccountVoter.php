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

namespace App\Security\Voter;

use App\Entity\Account;
use App\Security\ManagerPolicy;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides about permissions on accounts (mailboxes, aliases, mailing lists, send-only addresses).
 *
 * - Administrators and managers of the account's domain can manage all accounts of the domain.
 * - List managers can change settings and members of their mailing lists, and delete them.
 * - Only administrators and domain managers can appoint list managers.
 * - Members of a mailing list can leave it.
 * - Nobody can delete their own account.
 *
 * @extends Voter<string, Account>
 */
final class AccountVoter extends Voter
{
    /**
     * Edit the account (e.g. label, quota, password, alias destination, type, enabled state).
     */
    public const string MANAGE = 'ACCOUNT_MANAGE';

    /**
     * Delete the account.
     */
    public const string DELETE = 'ACCOUNT_DELETE';

    /**
     * Change the mailing list's settings and members, or delete the mailing list.
     */
    public const string LIST_MANAGE = 'LIST_MANAGE';

    /**
     * Appoint or remove list managers.
     */
    public const string LIST_MANAGE_MANAGERS = 'LIST_MANAGE_MANAGERS';

    /**
     * Remove oneself from the mailing list.
     */
    public const string LIST_LEAVE = 'LIST_LEAVE';

    /**
     * All attributes supported by this voter.
     */
    private const array ATTRIBUTES = [
        self::MANAGE,
        self::DELETE,
        self::LIST_MANAGE,
        self::LIST_MANAGE_MANAGERS,
        self::LIST_LEAVE
    ];

    /**
     * Attributes that only apply to mailing lists.
     */
    private const array LIST_ATTRIBUTES = [
        self::LIST_MANAGE,
        self::LIST_MANAGE_MANAGERS,
        self::LIST_LEAVE
    ];

    /**
     * Whether this voter supports the given attribute and subject.
     *
     * @see Voter::supports()
     */
    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Account && \in_array($attribute, self::ATTRIBUTES, true);
    }

    /**
     * Decides whether the current user is granted the attribute on the account.
     *
     * @see Voter::voteOnAttribute()
     */
    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Account || !$subject instanceof Account) {
            return false;
        }

        if (\in_array($attribute, self::LIST_ATTRIBUTES, true) && !$subject->isMailingList()) {
            return false;
        }

        return match ($attribute) {
            self::LIST_LEAVE => $this->policy->isListMember($user, $subject),
            self::DELETE => $subject !== $user && $this->managesDomainOf($token, $user, $subject),
            self::LIST_MANAGE => $this->policy->isListManager($user, $subject) || $this->managesDomainOf($token, $user, $subject),
            default => $this->managesDomainOf($token, $user, $subject)
        };
    }

    /**
     * Whether the user is an administrator or a manager of the account's domain.
     *
     * @param TokenInterface $token The security token.
     * @param Account $user The current user.
     * @param Account $subject The account to decide on.
     */
    private function managesDomainOf(TokenInterface $token, Account $user, Account $subject): bool
    {
        return $this->decisionManager->decide($token, ['ROLE_ADMIN']) || $subject->domain->isManagedBy($user);
    }

    /**
     * @param AccessDecisionManagerInterface $decisionManager The access decision manager (to check for admins).
     * @param ManagerPolicy $policy The rules for list managers and members.
     */
    public function __construct(
        private readonly AccessDecisionManagerInterface $decisionManager,
        private readonly ManagerPolicy $policy
    ) {
    }
}
