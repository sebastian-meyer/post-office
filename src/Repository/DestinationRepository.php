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

use App\Entity\Destination;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The repository for managed domains.
 *
 * @extends EntityRepository<Destination>
 */
final class DestinationRepository extends EntityRepository
{
    /**
     * Returns an existing or new destination.
     *
     * New destinations are registered for persistence, so they get saved with the next flush.
     *
     * @param string $email The destination email address.
     *
     * @return Destination The destination entry.
     */
    public function getEntity(string $email): Destination
    {
        $destination = $this->find($email);

        if ($destination === null) {
            $destination = new Destination($email);
            $this->getEntityManager()->persist($destination);
        }

        return $destination;
    }

    /**
     * @param ManagerRegistry $registry The object manager registry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Destination::class);
    }
}
