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

use App\Form\Model\ManagerData;
use App\Repository\AccountRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates the EligibleManager constraint.
 */
final class EligibleManagerValidator extends ConstraintValidator
{
    /**
     * Checks whether the email address belongs to an eligible manager.
     *
     * @param mixed $value The manager data.
     * @param Constraint $constraint The EligibleManager constraint.
     *
     * @see ConstraintValidator::validate()
     */
    #[\Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EligibleManager) {
            throw new UnexpectedTypeException($constraint, EligibleManager::class);
        }

        if (!$value instanceof ManagerData) {
            throw new UnexpectedValueException($value, ManagerData::class);
        }

        if ($value->email === null || $value->email === '') {
            return;
        }

        $account = $this->accountRepository->findOneByEmail($value->email);

        if ($account === null) {
            $this->context->buildViolation($constraint->notFoundMessage)->atPath('email')->addViolation();
        } elseif (!$value->domain->acceptsManager($account)) {
            $this->context->buildViolation($constraint->notEligibleMessage)
                ->setParameter('%domain%', (string) $value->domain)
                ->atPath('email')
                ->addViolation();
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
