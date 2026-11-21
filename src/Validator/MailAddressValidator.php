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
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates the MailAddress constraint with PHP's email validation (like the entities do).
 */
final class MailAddressValidator extends ConstraintValidator
{
    /**
     * Checks the email address.
     *
     * @param mixed $value The value to validate.
     * @param Constraint $constraint The constraint.
     *
     * @see ConstraintValidator::validate()
     */
    #[\Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MailAddress) {
            throw new UnexpectedTypeException($constraint, MailAddress::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (filter_var(trim($value), FILTER_VALIDATE_EMAIL) === false) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
