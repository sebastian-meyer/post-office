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
use App\Repository\AccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;
use Symfony\Bridge\Doctrine\Validator\Constraints;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A user account.
 *
 * This can be a mailbox, an alias, a mailing list, or a send-only email address (see AccountType).
 *
 * @property-read positive-int $id The auto-incremented unique identifier.
 * @property-read string $label The label for the account.
 * @property-read ?non-empty-string $username Optional username part of the user's email address.
 * @property-read Domain $domain The domain part of the user's email address.
 * @property-read ?non-empty-string $password The hashed password.
 * @property-read AccountType $type The type of the account.
 * @property-read Collection<int, Destination> $destinations Destination email addresses of aliases and mailing lists.
 * @property-read ?int<0, 5120> $quota The quota value for the user's mailbox (in megabytes).
 * @property-read bool $replyToList Whether responses should be sent back to the account's email address.
 * @property-read bool $strictList Whether only list members should be allowed to post to the list.
 * @property-read Collection<int, Account> $managers Additional account managers.
 * @property-read bool $isAdmin Whether the account has administrative privileges.
 * @property-read bool $enabled Whether the user's account is activated.
 */
#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'accounts')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', columns: ['username', 'domain'])]
#[Assert\Cascade]
#[Constraints\UniqueEntity(fields: ['username', 'domain'], message: 'accountAlreadyExists')]
class Account extends Entity implements PasswordAuthenticatedUserInterface, UserInterface
{
    use Trait\Identifiable;
    use Trait\Switchable;

    /**
     * The default quota for new mailboxes (in megabytes).
     */
    public const int DEFAULT_QUOTA = 100;

    /**
     * The maximum quota for mailboxes (in megabytes).
     */
    public const int MAX_QUOTA = 5120;

    /**
     * The label for the account.
     */
    #[ORM\Column(name: 'label', type:'string', length: 255, nullable: false)]
    #[Assert\NoSuspiciousCharacters]
    private string $label = '';

    /**
     * The username part of the account's email address.
     *
     * If this is not set, the account is a catch-all address that receives all email for its domain. Catch-all
     * accounts can only be aliases or mailing lists and always need at least one destination.
     */
    #[ORM\Column(name: 'username', type: 'string', length: 64, nullable: true)]
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Regex(pattern: "/^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+$/", message: 'invalidUsername')]
    private ?string $username;

    /**
     * The domain part of the account's email address.
     */
    #[ORM\ManyToOne(targetEntity: Domain::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(name: 'domain', referencedColumnName: 'domain', nullable: false)]
    private Domain $domain;

    /**
     * The hashed password.
     *
     * If this is NULL, the account is not allowed to log in.
     */
    #[ORM\Column(name: 'password', type: 'string', length: 255, nullable: true)]
    #[Assert\DisableAutoMapping]
    #[Assert\NotBlank(allowNull: true)]
    private ?string $password;

    /**
     * The type of the account.
     */
    #[ORM\Column(name: 'type', type: 'string', length: 16, nullable: false, enumType: AccountType::class, options: ['default' => 'mailbox'])]
    private AccountType $type = AccountType::Mailbox;

    /**
     * Destination email addresses for accounts that are aliases or mailing lists.
     *
     * An alias has exactly one destination, a mailing list has any number of destinations (its members). Mailboxes and
     * send-only accounts never have destinations.
     *
     * @var Collection<int, Destination>
     */
    #[ORM\ManyToMany(targetEntity: Destination::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'account_destinations')]
    #[ORM\JoinColumn(name: 'account', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'destination', referencedColumnName: 'destination')]
    private Collection $destinations;

    /**
     * The quota value for the account's mailbox (in megabytes).
     *
     * A quota of 0 means the account has no quota limit. This is always NULL for accounts that are not mailboxes.
     */
    #[ORM\Column(name: 'quota', type: 'integer', nullable: true, options: ['default' => self::DEFAULT_QUOTA, 'unsigned' => true])]
    #[Assert\Range(min: 0, max: self::MAX_QUOTA)]
    private ?int $quota = self::DEFAULT_QUOTA;

    /**
     * Whether responses should be sent back to the account's email address.
     *
     * This can only be TRUE for mailing lists.
     */
    #[ORM\Column(name: 'replytolist', type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $replyToList = false;

    /**
     * Whether only list members should be allowed to post to the list.
     *
     * This can only be TRUE for mailing lists.
     */
    #[ORM\Column(name: 'strictlist', type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $strictList = false;

    /**
     * Additional managers of the account.
     *
     * @var Collection<int, Account>
     */
    #[ORM\ManyToMany(targetEntity: Account::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'account_managers')]
    #[ORM\JoinColumn(name: 'account', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'manager', referencedColumnName: 'id')]
    private Collection $managers;

    /**
     * Whether the account has administrative privileges.
     */
    private bool $isAdmin = false;

    /**
     * Adds members to the mailing list.
     *
     * @param Destination ...$destinations The destination email addresses.
     *
     * @throws LogicException if the account is not a mailing list.
     */
    public function addDestinations(Destination ...$destinations): static
    {
        if ($this->type !== AccountType::MailingList) {
            throw new LogicException('Only mailing lists can have additional destinations. Call Account::makeAlias() to change the destination of an alias.');
        }

        foreach ($destinations as $destination) {
            if (!$this->destinations->contains($destination)) {
                $this->destinations->add($destination);
            }
        }

        return $this;
    }

    /**
     * Adds a manager for the account.
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
     * Removes sensitive data from the account.
     *
     * @see UserInterface::eraseCredentials()
     */
    #[\Deprecated]
    #[\Override]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    /**
     * Returns the hashed password used to authenticate the account.
     *
     * @return ?string The hashed password, or NULL if the account is not allowed to log in.
     *
     * @see PasswordAuthenticatedUserInterface::getPassword()
     */
    #[\Override]
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Returns the roles granted to the account.
     *
     * @return string[] The roles granted to the account.
     *
     * @see UserInterface::getRoles()
     */
    #[\Override]
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->isAdmin) {
            $roles[] = 'ROLE_ADMIN';
        }

        return $roles;
    }

    /**
     * Returns the identifier for this account (e.g. email).
     *
     * @return non-empty-string The identifier for this account.
     *
     * @see UserInterface::getUserIdentifier()
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        return "{$this->username}@{$this->domain}";
    }

    /**
     * Whether the account has a password (i.e. the user can log in).
     */
    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    /**
     * Sets up a newly created account according to its type.
     *
     * @param AccountType $type The type of the account.
     * @param list<Destination> $destinations The destination (for aliases) or members (for mailing lists).
     *
     * @throws InvalidArgumentException if the number of destinations doesn't match the account type.
     */
    private function initializeType(AccountType $type, array $destinations): void
    {
        if (!$type->acceptsDestinationCount(\count($destinations))) {
            throw new InvalidArgumentException(\sprintf('An account of type "%s" cannot have %d destination(s).', $type->value, \count($destinations)));
        }

        match ($type) {
            AccountType::Mailbox => $this->makeMailbox(),
            AccountType::Alias => $this->makeAlias($destinations[0] ?? throw new InvalidArgumentException('An alias requires a destination.')),
            AccountType::MailingList => $this->makeMailingList(...$destinations),
            AccountType::SendOnly => $this->makeSendOnly(),
        };
    }

    /**
     * Whether the account is an alias.
     */
    public function isAlias(): bool
    {
        return $this->type === AccountType::Alias;
    }

    /**
     * Whether the account is a catch-all email address.
     */
    public function isCatchAll(): bool
    {
        return $this->username === null;
    }

    /**
     * Whether the account is a mailbox.
     */
    public function isMailbox(): bool
    {
        return $this->type === AccountType::Mailbox;
    }

    /**
     * Whether the account is a mailing list.
     */
    public function isMailingList(): bool
    {
        return $this->type === AccountType::MailingList;
    }

    /**
     * Whether the account is only allowed to send email.
     */
    public function isSendOnly(): bool
    {
        return $this->type === AccountType::SendOnly;
    }

    /**
     * Makes the account an alias for the given destination.
     *
     * @param Destination $destination The destination email address.
     */
    public function makeAlias(Destination $destination): static
    {
        $this->type = AccountType::Alias;
        $this->destinations->clear();
        $this->destinations->add($destination);
        $this->quota = null;
        $this->replyToList = false;
        $this->strictList = false;

        return $this;
    }

    /**
     * Makes the account a mailbox with the given quota limit.
     *
     * @param int $quota The quota limit in megabytes. Must be between 0 and 5120, where 0 means unlimited.
     *
     * @throws LogicException if the account is a catch-all address.
     */
    public function makeMailbox(int $quota = self::DEFAULT_QUOTA): static
    {
        if ($this->username === null) {
            throw new LogicException('A catch-all account cannot be a mailbox.');
        }

        $this->type = AccountType::Mailbox;
        $this->destinations->clear();
        $this->replyToList = false;
        $this->strictList = false;

        return $this->setQuota($quota);
    }

    /**
     * Makes the account a mailing list with the given members.
     *
     * @param Destination ...$destinations The destination email addresses.
     *
     * @throws LogicException if the account is a catch-all address and no destination is given.
     */
    public function makeMailingList(Destination ...$destinations): static
    {
        if ($this->username === null && \count($destinations) === 0) {
            throw new LogicException('A catch-all account requires at least one destination.');
        }

        $this->type = AccountType::MailingList;
        $this->destinations->clear();
        $this->quota = null;

        return $this->addDestinations(...$destinations);
    }

    /**
     * Makes the account send-only by removing destinations and quota limit.
     *
     * @throws LogicException if the account is a catch-all address.
     */
    public function makeSendOnly(): static
    {
        if ($this->username === null) {
            throw new LogicException('A catch-all account cannot be send-only.');
        }

        $this->type = AccountType::SendOnly;
        $this->destinations->clear();
        $this->quota = null;
        $this->replyToList = false;
        $this->strictList = false;

        return $this;
    }

    /**
     * Removes a member from the mailing list.
     *
     * @param Destination $destination The email address to remove from the destinations.
     *
     * @throws LogicException if the account is not a mailing list, or if it is a catch-all address and the last
     *                        destination is about to be removed.
     */
    public function removeDestination(Destination $destination): static
    {
        if ($this->type !== AccountType::MailingList) {
            throw new LogicException('Only mailing lists can have destinations removed. Call Account::makeAlias() to change the destination of an alias.');
        }

        if ($this->username === null && $this->destinations->count() === 1 && $this->destinations->contains($destination)) {
            throw new LogicException('A catch-all account must have at least one destination.');
        }

        $this->destinations->removeElement($destination);

        return $this;
    }

    /**
     * Removes a manager for the account.
     *
     * @param Account $account The account to remove from the managers.
     */
    public function removeManager(Account $account): static
    {
        $this->managers->removeElement($account);

        return $this;
    }

    /**
     * Sets the administrative status of the account.
     *
     * @param bool $isAdmin Whether the account should have administrative rights.
     */
    public function setAdmin(bool $isAdmin): static
    {
        $this->isAdmin = $isAdmin;

        return $this;
    }

    /**
     * Sets the label for the account.
     *
     * @param string $label The label for the account.
     */
    public function setLabel(string $label): static
    {
        $this->label = trim($label);

        return $this;
    }

    /**
     * Updates the hashed password used to authenticate the account.
     *
     * @param ?string $hashedPassword The hashed password using algorithm bcrypt(10), or NULL to disable login.
     *
     * @see AccountRepository::upgradePassword()
     */
    public function setPassword(?string $hashedPassword): static
    {
        $this->password = $hashedPassword;

        return $this;
    }

    /**
     * Sets the quota value for the account's mailbox.
     *
     * @param int $quota The quota value in megabytes. Must be between 0 and 5120, where 0 means unlimited.
     *
     * @throws LogicException if the account is not a mailbox.
     */
    public function setQuota(int $quota): static
    {
        if ($this->type !== AccountType::Mailbox) {
            throw new LogicException('Only mailboxes can have a quota limit.');
        }

        $this->quota = max(0, min($quota, self::MAX_QUOTA));

        return $this;
    }

    /**
     * Sets the reply mode for the mailing list.
     *
     * @param bool $replyToList Whether responses should be sent back to the mailing list.
     *
     * @throws LogicException if the account is not a mailing list.
     */
    public function setReplyToList(bool $replyToList): static
    {
        if ($replyToList && $this->type !== AccountType::MailingList) {
            throw new LogicException('Only mailing lists can set a Reply-To header.');
        }

        $this->replyToList = $replyToList;

        return $this;
    }

    /**
     * Sets the strict list mode for the mailing list.
     *
     * @param bool $strictList Whether only list members should be allowed to post to the list.
     *
     * @throws LogicException if the account is not a mailing list.
     */
    public function setStrictList(bool $strictList): static
    {
        if ($strictList && $this->type !== AccountType::MailingList) {
            throw new LogicException('Only mailing lists can enforce a strict list mode.');
        }

        $this->strictList = $strictList;

        return $this;
    }

    /**
     * @param ?string $username The username part of the account's email address, or NULL for a catch-all account.
     * @param Domain $domain The domain part of the account's email address.
     * @param AccountType $type The type of the account.
     * @param ?string $hashedPassword The hashed password using algorithm bcrypt(10), or NULL to disable login.
     * @param Destination ...$destinations The destination (for aliases) or members (for mailing lists).
     *
     * @throws InvalidArgumentException if username and domain don't form a valid email address, or if the number of
     *                                  destinations doesn't match the account type.
     * @throws LogicException if a catch-all account is not an alias or mailing list with at least one destination.
     */
    public function __construct(
        ?string $username,
        Domain $domain,
        AccountType $type = AccountType::Mailbox,
        ?string $hashedPassword = null,
        Destination ...$destinations
    ) {
        if ($username !== null && filter_var("{$username}@{$domain}", FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(\sprintf('Invalid email address "%s".', "{$username}@{$domain}"));
        }

        $this->username = $username;
        $this->domain = $domain;
        $this->password = $hashedPassword;
        $this->destinations = new ArrayCollection();
        $this->managers = new ArrayCollection();

        $this->initializeType($type, array_values($destinations));
    }

    /**
     * Ensure the session doesn't contain actual password hashes.
     *
     * @see PasswordAuthenticatedUserInterface
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', (string) $this->password);

        return $data;
    }

    /**
     * String representation of the account.
     */
    public function __toString(): string
    {
        return "{$this->username}@{$this->domain}";
    }
}
