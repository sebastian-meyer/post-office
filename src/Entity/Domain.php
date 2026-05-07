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

use App\Repository\DomainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A managed domain.
 *
 * @property-read non-empty-string $domain The managed domain name.
 * @property-read Collection<int, Account> $managers The managers of the domain.
 */
#[ORM\Entity(repositoryClass: DomainRepository::class)]
#[ORM\Table(name: 'domains')]
#[Assert\Cascade]
#[Constraints\UniqueEntity(fields: 'domain', message: 'domainAlreadyExists')]
class Domain extends Entity
{
    /**
     * The managed domain name.
     */
    #[ORM\Id]
    #[ORM\Column(name: 'domain', type: 'string', length: 180, unique: true, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Hostname]
    private string $domain;

    /**
     * The managers of the domain.
     *
     * @var Collection<int, Account>
     */
    #[ORM\JoinTable(name: 'domain_managers')]
    #[ORM\JoinColumn(name: 'domain', referencedColumnName: 'domain')]
    #[ORM\InverseJoinColumn(name: 'account', referencedColumnName: 'id')]
    #[ORM\ManyToMany(targetEntity: Account::class)]
    private Collection $managers;

    /**
     * Adds a manager for this domain.
     *
     * @param Account $account The account to add as a manager.
     */
    public function addManager(Account $account): static
    {
        if (!$this->managers->contains($account)) {
            $this->managers->add($account);
        }

        return $this;
    }

    /**
     * Removes a manager from this domain.
     *
     * @param Account $account The account to remove from the managers.
     */
    public function removeManager(Account $account): static
    {
        $this->managers->removeElement($account);

        return $this;
    }

    /**
     * @param string $domain The domain name.
     *
     * @throws \InvalidArgumentException if the provided domain name is invalid.
     */
    public function __construct(string $domain)
    {
        if (filter_var(trim($domain), FILTER_VALIDATE_DOMAIN) === false) {
            throw new \InvalidArgumentException(\sprintf('Invalid domain name "%s".', $domain));
        }

        $this->domain = trim($domain);
        $this->managers = new ArrayCollection();
    }

    /**
     * String representation of the domain.
     */
    public function __toString(): string
    {
        return $this->domain;
    }
}
