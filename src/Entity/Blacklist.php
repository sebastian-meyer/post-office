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
use App\Repository\BlacklistRepository;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Validator\Constraints;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A blacklisted sender.
 *
 * @property-read positive-int $id The auto-incremented unique identifier.
 * @property-read ?non-empty-string $username The username part of the blacklisted email address.
 * @property-read non-empty-string $domain The domain part of the blacklisted email address.
 * @property-read non-empty-string $action The action to take for blacklisted senders (DISCARD or REJECT).
 * @property-read bool $enabled Whether the blacklisting is active.
 */
#[ORM\Entity(repositoryClass: BlacklistRepository::class)]
#[ORM\Table(name: 'blacklist')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', columns: ['username', 'domain'])]
#[Assert\Cascade]
#[Constraints\UniqueEntity(fields: ['username', 'domain'], message: 'emailAlreadyExists')]
class Blacklist extends Entity
{
    use Trait\Identifiable;
    use Trait\Switchable;

    /**
     * Possible actions for blacklisted email addresses.
     */
    private const array ACTIONS = [
        'DISCARD',
        'REJECT'
    ];

    /**
     * The username part of the blacklisted email address, or NULL for domain wildcard.
     */
    #[ORM\Column(name: 'username', type: 'string', length: 64, nullable: true)]
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Regex(pattern: "/^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+$/", message: 'invalidUsername')]
    private ?string $username;

    /**
     * The domain part of the blacklisted email address.
     */
    #[ORM\Column(name: 'domain', type: 'string', length: 180, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Hostname]
    private string $domain;

    /**
     * The action to take for blacklisted senders.
     *
     * Possible values are:
     * - DISCARD: Silently discard the email without notifying the sender.
     * - REJECT: Reject the email and notify the sender with a bounce message.
     */
    #[ORM\Column(name: 'action', type: 'string', nullable: false, options: ['default' => 'REJECT'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: self::ACTIONS)]
    private string $action = 'REJECT';

    /**
     * Sets the action for the blacklisted email address.
     *
     * @param string $action The action to take for the sender (DISCARD or REJECT).
     *
     * @throws InvalidArgumentException if the action is invalid.
     */
    public function setAction(string $action): static
    {
        $action = trim(strtoupper($action));

        if (!\in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid action "%s". Must be one of %s.', $action, implode(', ', self::ACTIONS)));
        }

        $this->action = $action;

        return $this;
    }

    /**
     * @param string $emailOrDomain The email address or domain to blacklist.
     *
     * @throws InvalidArgumentException if the email address or domain is invalid.
     */
    public function __construct(string $emailOrDomain)
    {
        $emailOrDomain = trim($emailOrDomain);

        if (filter_var($emailOrDomain, FILTER_VALIDATE_EMAIL) !== false) {
            [$username, $hostname] = explode('@', $emailOrDomain, 2) + [1 => null];
        } else {
            $username = null;
            $hostname = filter_var(trim($emailOrDomain, '@.'), FILTER_VALIDATE_DOMAIN, FILTER_NULL_ON_FAILURE);
        }

        if ($hostname === null) {
            throw new InvalidArgumentException(\sprintf('Invalid email address or domain name "%s".', $emailOrDomain));
        }

        $this->username = $username;
        $this->domain = $hostname;
    }
}
