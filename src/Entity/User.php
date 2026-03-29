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

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user account.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'accounts')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', columns: ['username', 'domain'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * The auto-incremented unique identifier.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', name: 'id')]
    private int $id;

    /**
     * The username part of the user's email address.
     */
    #[ORM\Column(type: 'string', name: 'username', length: 64, nullable: false)]
    private string $username;

    /**
     * The domain part of the user's email address.
     */
    #[ORM\Column(type: 'string', name: 'domain', length: 180, nullable: false)]
    private string $domain;

    /**
     * The hashed password.
     */
    #[ORM\Column(type: 'string', name: 'password', length: 60, nullable: false)]
    private string $password;

    /**
     * The quota value for the user's mailbox.
     */
    #[ORM\Column(type: 'smallint', name: 'quota', options: ['default' => 0, 'unsigned' => true])]
    private int $quota = 0;

    /**
     * Whether the user's account is activated.
     */
    #[ORM\Column(type: 'boolean', name: 'enabled', options: ['default' => true])]
    private bool $enabled = true;

    /**
     * Whether the user's account is send-only.
     */
    #[ORM\Column(type: 'boolean', name: 'sendonly', options: ['default' => false])]
    private bool $sendonly = false;

    /**
     * Removes sensitive data from the user.
     *
     * @see UserInterface
     */
    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    /**
     * Returns the hashed password used to authenticate the user.
     *
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Returns the roles granted to the user.
     *
     * @see UserInterface
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    /**
     * Returns the identifier for this user (e.g. email).
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->username . '@' . $this->domain;
    }

    /**
     * Updates the hashed password used to authenticate the user.
     *
     * @param string The hashed password using algorithm bcrypt(10)
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * Ensure the session doesn't contain actual password hashes.
     *
     * @see PasswordAuthenticatedUserInterface
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);
        return $data;
    }
}
