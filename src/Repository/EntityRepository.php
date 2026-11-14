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

namespace App\Repository;

use App\Entity\Entity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * The base class for repositories.
 *
 * @template T of Entity
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class EntityRepository extends ServiceEntityRepository
{
    /**
     * The validator service instance.
     */
    protected readonly ValidatorInterface $validator;

    /**
     * Validates an entity and saves it to the database.
     *
     * @param T $entity The entity to save.
     *
     * @throws ValidationFailedException if the entity is not valid.
     */
    public function save(Entity $entity): void
    {
        $violations = $this->validator->validate($entity);

        if ($violations->count() > 0) {
            throw new ValidationFailedException($entity, $violations);
        }

        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    /**
     * Sets the validator service during instantiation.
     *
     * @param ValidatorInterface $validator The validator service instance.
     */
    #[Required]
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }
}
