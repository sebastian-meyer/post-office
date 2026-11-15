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

namespace App\Form\Model;

use App\Entity\TlsPolicy;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form data for creating or editing a TLS policy.
 *
 * The domain can only be set when creating a policy (validation group "create").
 */
#[UniqueEntity(fields: ['domain'], message: 'domainAlreadyExists', entityClass: TlsPolicy::class, groups: ['create'])]
final class TlsPolicyData
{
    /**
     * The recipient domain (a leading dot includes all subdomains, as in Postfix).
     */
    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Length(max: 180, groups: ['create'])]
    #[Assert\Regex(pattern: '/^\.?([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', message: 'invalidDomain', groups: ['create'])]
    public ?string $domain = null;

    /**
     * The TLS policy.
     */
    #[Assert\NotBlank]
    #[Assert\Choice(choices: TlsPolicy::POLICIES)]
    public ?string $policy = 'encrypt';

    /**
     * Additional policy parameters.
     */
    #[Assert\Length(max: 255)]
    #[Assert\NoSuspiciousCharacters]
    public ?string $params = null;

    /**
     * Whether the policy is enabled.
     */
    public bool $enabled = true;

    /**
     * Creates the form data from an existing policy.
     *
     * @param TlsPolicy $tlsPolicy The policy.
     */
    public static function fromTlsPolicy(TlsPolicy $tlsPolicy): self
    {
        $data = new self();
        $data->domain = $tlsPolicy->domain;
        $data->policy = $tlsPolicy->policy;
        $data->params = $tlsPolicy->params;
        $data->enabled = $tlsPolicy->enabled;

        return $data;
    }
}
