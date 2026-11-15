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

namespace App\Entity;

use App\Entity\Trait;
use App\Repository\TlsPolicyRepository;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Validator\Constraints;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A recipient TLS policy.
 *
 * @property-read non-empty-string $domain The recipient's domain name.
 * @property-read non-empty-string $policy The policy for the recipient.
 * @property-read string $params Additional policy parameters.
 * @property-read bool $enabled Whether the policy is active.
 */
#[ORM\Entity(repositoryClass: TlsPolicyRepository::class)]
#[ORM\Table(name: 'tlspolicies')]
#[Assert\Cascade]
#[Constraints\UniqueEntity(fields: 'domain', message: 'domainAlreadyExists')]
class TlsPolicy extends Entity
{
    use Trait\Switchable;

    /**
     * List of valid TLS policies.
     */
    public const array POLICIES = [
        'none',
        'may',
        'encrypt',
        'dane',
        'dane-only',
        'fingerprint',
        'verify',
        'secure'
    ];

    /**
     * The recipient's domain name.
     *
     * A leading dot (e.g. ".example.com") includes all subdomains, as in Postfix.
     */
    #[ORM\Id]
    #[ORM\Column(name: 'domain', type: 'string', length: 180, unique: true, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\.?([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', message: 'invalidDomain')]
    private string $domain;

    /**
     * The policy for the recipient.
     */
    #[ORM\Column(name: 'policy', type: 'string', nullable: false, options: ['default' => 'none'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: self::POLICIES)]
    private string $policy = 'none';

    /**
     * Additional policy parameters.
     */
    #[ORM\Column(name: 'params', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    #[Assert\NoSuspiciousCharacters]
    private string $params = '';

    /**
     * Sets the TLS policy for the domain.
     *
     * @param string $policy The TLS policy to set (none, may, encrypt, dane, dane-only, fingerprint, verify, secure).
     *
     * @throws InvalidArgumentException if the policy is invalid.
     */
    public function setPolicy(string $policy): static
    {
        $policy = trim(strtolower($policy));

        if (!\in_array($policy, self::POLICIES, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid policy "%s". Must be one of %s.', $policy, implode(', ', self::POLICIES)));
        }

        $this->policy = $policy;

        return $this;
    }

    /**
     * Sets additional policy parameters.
     *
     * @param string $params The additional policy parameters.
     */
    public function setParams(string $params): static
    {
        $this->params = trim($params);

        return $this;
    }

    /**
     * @param string $domain The domain name.
     *
     * @throws InvalidArgumentException if the provided domain name is invalid.
     */
    public function __construct(string $domain)
    {
        if (filter_var(trim(trim($domain), '.'), FILTER_VALIDATE_DOMAIN) === false) {
            throw new InvalidArgumentException(\sprintf('Invalid domain name "%s".', $domain));
        }

        $this->domain = trim($domain);
    }
}
