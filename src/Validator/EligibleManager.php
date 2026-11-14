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

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Ensures that the given email address belongs to an account that can be a manager for the domain.
 *
 * Applies to ManagerData objects. Only enabled mailboxes of the same domain can be managers.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class EligibleManager extends Constraint
{
    /**
     * The violation message if no account exists (translation key).
     */
    public string $notFoundMessage = 'managerNotFound';

    /**
     * The violation message if the account cannot be a manager (translation key).
     */
    public string $notEligibleMessage = 'managerNotEligible';

    /**
     * This is a class constraint.
     *
     * @see Constraint::getTargets()
     */
    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
