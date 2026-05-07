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

use App\Entity\Blacklist;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The repository for blacklisted senders.
 *
 * @extends EntityRepository<Blacklist>
 */
final class BlacklistRepository extends EntityRepository
{
    /**
     * Returns an existing or new blacklisted sender.
     *
     * @param string $emailOrDomain The email address or domain to blacklist.
     * @param ?string $action The action to take for the sender (DISCARD or REJECT).
     *
     * @return Blacklist The blacklist entry.
     */
    public function getEntity(string $emailOrDomain, ?string $action = null): Blacklist
    {
        $blacklist = $this->find($emailOrDomain);

        if ($blacklist === null) {
            $blacklist = new Blacklist($emailOrDomain);
        }

        if ($action !== null) {
            $blacklist->setAction($action);
        }

        return $blacklist;
    }

    /**
     * @param ManagerRegistry $registry The object manager registry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Blacklist::class);
    }
}
