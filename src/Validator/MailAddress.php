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
 * The value must be an email address that the entities accept (see Destination and Account).
 *
 * Symfony's Email constraint (html5 mode) accepts addresses like "a..b@example.com", which PHP's email validation used
 * by the entities rejects. Use this constraint in addition to Assert\Email.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class MailAddress extends Constraint
{
    /**
     * The violation message (translation key).
     */
    public string $message = 'invalidEmail';
}
