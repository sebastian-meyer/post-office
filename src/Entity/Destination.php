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

use App\Repository\DestinationRepository;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Validator\Constraints;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A mail destination.
 *
 * @property-read non-empty-string $destination The destination email address.
 */
#[ORM\Entity(repositoryClass: DestinationRepository::class)]
#[ORM\Table(name: 'destinations')]
#[Constraints\UniqueEntity(fields: 'destination', message: 'emailAlreadyExists')]
class Destination extends Entity
{
    /**
     * The destination email address.
     */
    #[ORM\Id]
    #[ORM\Column(name: 'destination', type: 'string', length: 254, unique: true, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $destination;

    /**
     * @param string $destination The destination email address.
     *
     * @throws InvalidArgumentException if the provided email address is invalid.
     */
    public function __construct(string $destination)
    {
        if (filter_var(trim($destination), FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(\sprintf('Invalid email address "%s".', $destination));
        }

        $this->destination = trim($destination);
    }

    /**
     * String representation of the destination.
     */
    public function __toString(): string
    {
        return $this->destination;
    }
}
