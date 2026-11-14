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

use App\Form\Model\AccountData;
use App\Repository\AccountRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates the AvailableAddress constraint.
 */
final class AvailableAddressValidator extends ConstraintValidator
{
    /**
     * Checks whether an account with the given address already exists.
     *
     * @param mixed $value The account data.
     * @param Constraint $constraint The AvailableAddress constraint.
     *
     * @see ConstraintValidator::validate()
     */
    #[\Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AvailableAddress) {
            throw new UnexpectedTypeException($constraint, AvailableAddress::class);
        }

        if (!$value instanceof AccountData) {
            throw new UnexpectedValueException($value, AccountData::class);
        }

        if ($value->domain === null) {
            return;
        }

        // A plain unique index does not prevent multiple catch-all addresses (NULL usernames), so check explicitly.
        if ($this->accountRepository->findOneBy(['username' => $value->username, 'domain' => $value->domain]) !== null) {
            $this->context->buildViolation($constraint->message)->atPath('username')->addViolation();
        }
    }

    /**
     * @param AccountRepository $accountRepository The repository for accounts.
     */
    public function __construct(
        private readonly AccountRepository $accountRepository
    ) {
    }
}
