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

use App\Entity\TlsPolicy;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The repository for TLS policies.
 *
 * @extends EntityRepository<TlsPolicy>
 */
final class TlsPolicyRepository extends EntityRepository
{
    /**
     * Returns an existing or new recipient TLS policy.
     *
     * @param string $domain The domain name.
     * @param ?string $policy The TLS policy to set (none, may, encrypt, dane, dane-only, fingerprint, verify, secure).
     * @param ?string $params The additional policy parameters.
     *
     * @return TlsPolicy The policy entry.
     */
    public function getEntity(string $domain, ?string $policy = null, ?string $params = null): TlsPolicy
    {
        $tlsPolicy = $this->find($domain);

        if ($tlsPolicy === null) {
            $tlsPolicy = new TlsPolicy($domain);
        }

        if ($policy !== null) {
            $tlsPolicy->setPolicy($policy);
        }

        if ($params !== null) {
            $tlsPolicy->setParams($params);
        }

        return $tlsPolicy;
    }

    /**
     * @param ManagerRegistry $registry The object manager registry.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TlsPolicy::class);
    }
}
