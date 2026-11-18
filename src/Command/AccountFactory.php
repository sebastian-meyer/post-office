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

namespace App\Command;

use App\Entity\Account;
use App\Form\Model\AccountData;
use App\Form\Model\AliasData;
use App\Form\Model\MailboxData;
use App\Form\Model\SendOnlyData;
use App\Repository\DomainRepository;
use App\Service\AccountManager;
use InvalidArgumentException;

/**
 * Creates accounts from the arguments and options of a console command.
 *
 * @see AddAccountCommand
 */
final class AccountFactory
{
    /**
     * Creates the account data (without password) for a mailbox, send-only address or alias.
     *
     * @param string $email The email address of the new account, or "@domain" for a catch-all alias.
     * @param string $name The name of the account's owner.
     * @param ?string $quota The storage quota in megabytes (mailboxes only).
     * @param bool $sendOnly Whether to create a send-only address instead of a mailbox.
     * @param ?string $alias The destination address, to create an alias instead of a mailbox.
     *
     * @throws InvalidArgumentException if the options conflict, the email address is invalid, or its domain doesn't
     *                                  exist.
     */
    public function createData(string $email, string $name, ?string $quota, bool $sendOnly, ?string $alias): AccountData
    {
        if ($sendOnly && $alias !== null) {
            throw new InvalidArgumentException('The options --send-only and --alias cannot be combined.');
        }

        if ($quota !== null && ($sendOnly || $alias !== null)) {
            throw new InvalidArgumentException('Only mailboxes have a storage quota.');
        }

        $data = match (true) {
            $alias !== null => new AliasData(),
            $sendOnly => new SendOnlyData(),
            default => new MailboxData()
        };
        $this->setAddress($data, $email);
        $data->label = $name;

        if ($data instanceof AliasData) {
            $data->destination = trim((string) $alias);
        }

        if ($data instanceof MailboxData && $quota !== null) {
            $data->quota = filter_var($quota, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
                ?? throw new InvalidArgumentException(\sprintf('The quota "%s" is not a number.', $quota));
        }

        return $data;
    }

    /**
     * Saves a new account from the validated account data.
     *
     * @param AccountData $data The validated account data.
     *
     * @throws InvalidArgumentException if the type of account data is not supported.
     */
    public function save(AccountData $data): Account
    {
        return match (true) {
            $data instanceof MailboxData => $this->accountManager->createMailbox($data),
            $data instanceof SendOnlyData => $this->accountManager->createSendOnly($data),
            $data instanceof AliasData => $this->accountManager->createAlias($data),
            default => throw new InvalidArgumentException(\sprintf('Unsupported account data "%s".', $data::class))
        };
    }

    /**
     * Sets username and (existing) domain of the account data from an email address.
     *
     * Only aliases may have an empty username (i.e. "@example.com" for a catch-all address).
     *
     * @param AccountData $data The account data.
     * @param string $email The email address.
     *
     * @throws InvalidArgumentException if the email address is invalid or its domain doesn't exist.
     */
    private function setAddress(AccountData $data, string $email): void
    {
        $position = strrpos($email, '@');
        $username = $position !== false ? trim(substr($email, 0, $position)) : '';

        if ($position === false || ($username === '' && !$data instanceof AliasData)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a valid email address.', $email));
        }

        $hostname = strtolower(trim(substr($email, $position + 1)));
        $data->username = $username !== '' ? $username : null;
        $data->domain = $this->domainRepository->find($hostname) ?? throw new InvalidArgumentException(\sprintf(
            'The domain %s does not exist. Add it with "php bin/console app:domain:add %s" first.',
            $hostname,
            $hostname
        ));
    }

    /**
     * @param AccountManager $accountManager The service for creating accounts.
     * @param DomainRepository $domainRepository The repository for domains.
     */
    public function __construct(
        private readonly AccountManager $accountManager,
        private readonly DomainRepository $domainRepository
    ) {
    }
}
