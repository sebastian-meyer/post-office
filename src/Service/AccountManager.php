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

namespace App\Service;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Form\Model\AccountData;
use App\Form\Model\AliasData;
use App\Form\Model\MailboxData;
use App\Form\Model\MailingListData;
use App\Repository\AccountRepository;
use App\Repository\DestinationRepository;
use LogicException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Creates, updates and deletes accounts.
 *
 * The form data is expected to be validated already; the resulting entities are validated again before saving.
 * Authorization is not checked here, but by the controllers (see App\Security\Voter).
 */
final class AccountManager
{
    /**
     * Creates a new mailbox.
     *
     * @param MailboxData $data The validated form data.
     *
     * @throws LogicException if username, domain or password are missing.
     * @throws ValidationFailedException if the new account is not valid.
     */
    public function createMailbox(MailboxData $data): Account
    {
        if ($data->plainPassword === null || $data->plainPassword === '') {
            throw new LogicException('A new mailbox requires a password.');
        }

        $account = $this->newAccount($data, AccountType::Mailbox);
        $account->setQuota($data->quota ?? Account::DEFAULT_QUOTA);
        $account->setPassword($this->hasher->hashPassword($account, $data->plainPassword));

        $this->accountRepository->save($account);

        return $account;
    }

    /**
     * Creates a new alias (or catch-all alias).
     *
     * @param AliasData $data The validated form data.
     *
     * @throws LogicException if domain or destination are missing.
     * @throws ValidationFailedException if the new account is not valid.
     */
    public function createAlias(AliasData $data): Account
    {
        $account = $this->newAccount($data, AccountType::Alias, $data->destination);

        $this->accountRepository->save($account);

        return $account;
    }

    /**
     * Creates a new mailing list without members.
     *
     * @param MailingListData $data The validated form data.
     *
     * @throws LogicException if the domain is missing, or if the list would be a catch-all address without members.
     * @throws ValidationFailedException if the new account is not valid.
     */
    public function createMailingList(MailingListData $data): Account
    {
        $account = $this->newAccount($data, AccountType::MailingList);
        $account->setListOptions($data->replyToList, $data->strictList);

        $this->accountRepository->save($account);

        return $account;
    }

    /**
     * Deletes an account.
     *
     * The account is removed from all manager roles first. Note that the mail storage of a mailbox is not deleted.
     *
     * @param Account $account The account to delete.
     */
    public function delete(Account $account): void
    {
        $this->membershipManager->revokeManagerRoles($account);

        $this->accountRepository->remove($account);
    }

    /**
     * Updates an existing alias.
     *
     * @param Account $account The alias.
     * @param AliasData $data The validated form data.
     *
     * @throws LogicException if the account is not an alias, or the destination is missing.
     * @throws ValidationFailedException if the account is not valid afterwards.
     */
    public function updateAlias(Account $account, AliasData $data): void
    {
        $destination = $data->destination;

        if (!$account->isAlias() || $destination === null) {
            throw new LogicException('Invalid alias data.');
        }

        $this->updateCommon($account, $data);
        $account->makeAlias($this->destinationRepo->getEntity($destination));

        $this->accountRepository->save($account);
    }

    /**
     * Updates an existing mailbox.
     *
     * If the mailbox gets disabled, it loses all manager roles.
     *
     * @param Account $account The mailbox.
     * @param MailboxData $data The validated form data. An empty password leaves the password unchanged.
     *
     * @throws LogicException if the account is not a mailbox.
     * @throws ValidationFailedException if the account is not valid afterwards.
     */
    public function updateMailbox(Account $account, MailboxData $data): void
    {
        if (!$account->isMailbox()) {
            throw new LogicException('Only mailboxes can be updated with mailbox data.');
        }

        $this->updateCommon($account, $data);
        $account->setQuota($data->quota ?? Account::DEFAULT_QUOTA);

        if ($data->plainPassword !== null && $data->plainPassword !== '') {
            $account->setPassword($this->hasher->hashPassword($account, $data->plainPassword));
        }

        if (!$data->enabled) {
            $this->membershipManager->revokeManagerRoles($account);
        }

        $this->accountRepository->save($account);
    }

    /**
     * Updates the settings of an existing mailing list.
     *
     * @param Account $account The mailing list.
     * @param MailingListData $data The validated form data.
     *
     * @throws LogicException if the account is not a mailing list.
     * @throws ValidationFailedException if the account is not valid afterwards.
     */
    public function updateMailingList(Account $account, MailingListData $data): void
    {
        $this->updateCommon($account, $data);
        $account->setListOptions($data->replyToList, $data->strictList);

        $this->accountRepository->save($account);
    }

    /**
     * Creates a new (not yet saved) account from form data.
     *
     * @param AccountData $data The validated form data.
     * @param AccountType $type The type of the new account.
     * @param ?string $destination The destination for aliases.
     *
     * @throws LogicException if the domain is missing.
     */
    private function newAccount(AccountData $data, AccountType $type, ?string $destination = null): Account
    {
        if ($data->domain === null) {
            throw new LogicException('A new account requires a domain.');
        }

        $destinations = $destination !== null ? [$this->destinationRepo->getEntity($destination)] : [];

        $account = new Account($data->username, $data->domain, $type, null, ...$destinations);
        $this->updateCommon($account, $data);

        return $account;
    }

    /**
     * Applies the fields common to all account types.
     *
     * @param Account $account The account.
     * @param AccountData $data The validated form data.
     */
    private function updateCommon(Account $account, AccountData $data): void
    {
        $account->setLabel($data->label ?? '');

        if ($data->enabled) {
            $account->enable();
        } else {
            $account->disable();
        }
    }

    /**
     * @param AccountRepository $accountRepository The repository for accounts.
     * @param DestinationRepository $destinationRepo The repository for destinations.
     * @param MembershipManager $membershipManager The service for memberships and manager roles.
     * @param UserPasswordHasherInterface $hasher The password hasher.
     */
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly DestinationRepository $destinationRepo,
        private readonly MembershipManager $membershipManager,
        private readonly UserPasswordHasherInterface $hasher
    ) {
    }
}
