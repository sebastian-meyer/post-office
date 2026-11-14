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
use App\Entity\Domain;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides about permissions on domains.
 *
 * - Administrators can do everything.
 * - Domain managers can manage the domain, i.e. create, edit and delete its accounts and appoint list managers.
 * - Only administrators can appoint domain managers.
 *
 * @extends Voter<string, Domain>
 */
final class DomainVoter extends Voter
{
    /**
     * Manage the domain as a whole: list its accounts and create new ones.
     *
     * For existing accounts, use the attributes of AccountVoter instead.
     */
    public const string MANAGE = 'DOMAIN_MANAGE';

    /**
     * Appoint or remove domain managers.
     */
    public const string MANAGE_MANAGERS = 'DOMAIN_MANAGE_MANAGERS';

    /**
     * Whether this voter supports the given attribute and subject.
     *
     * @see Voter::supports()
     */
    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Domain && \in_array($attribute, [self::MANAGE, self::MANAGE_MANAGERS], true);
    }

    /**
     * Decides whether the current user is granted the attribute on the domain.
     *
     * @see Voter::voteOnAttribute()
     */
    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Account || !$subject instanceof Domain) {
            return false;
        }

        if ($this->decisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return $attribute === self::MANAGE && $subject->isManagedBy($user);
    }

    /**
     * @param AccessDecisionManagerInterface $decisionManager The access decision manager (to check for admins).
     */
    public function __construct(
        private readonly AccessDecisionManagerInterface $decisionManager
    ) {
    }
}
