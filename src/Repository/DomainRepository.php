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

use App\Entity\Account;
use App\Entity\Domain;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * The repository for managed domains.
 *
 * @extends EntityRepository<Domain>
 */
final class DomainRepository extends EntityRepository
{
    /**
     * Returns all domains the given account is a domain manager of.
     *
     * Note that this includes domains for which the account is no longer eligible as manager (e.g. because it is
     * disabled), so use Domain::isManagedBy() for authorization decisions.
     *
     * @param Account $manager The manager.
     *
     * @return list<Domain> The domains, ordered by name.
     */
    public function findByManager(Account $manager): array
    {
        return $this->getEntityResult(
            $this->createQueryBuilder('domain')
                ->andWhere(':manager MEMBER OF domain.managers')->setParameter('manager', $manager->id, Types::INTEGER)
                ->addOrderBy('domain.domain', SortDirection::Ascending)
        );
    }

    /**
     * @param ManagerRegistry $registry The object manager registry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Domain::class);
    }
}
