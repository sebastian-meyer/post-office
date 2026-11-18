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
use App\Form\Model\AliasData;
use App\Form\Model\MailboxData;
use App\Form\Model\MailingListData;
use App\Form\Model\SendOnlyData;
use App\Repository\AccountRepository;
use App\Repository\DestinationRepository;
use LogicException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Creates, updates, enables, disables and deletes accounts.
 *
 * The form data is expected to be validated already; the resulting entities are validated again before saving.
 * Authorization is not checked here, but by the controllers (see App\Security\Voter).
 */
final class AccountManager
{
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
        if ($data->destination === null) {
            throw new LogicException('A new alias requires a destination.');
        }

        $account = $data->createAccount(AccountType::Alias, $this->destinationRepo->getEntity($data->destination));
        $this->accountRepository->save($account);

        return $account;
    }

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
        $account = $data->createAccount(AccountType::Mailbox);
        $account->setQuota($data->quota ?? Account::DEFAULT_QUOTA);

        return $this->saveNew($account, $data);
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
        $account = $data->createAccount(AccountType::MailingList);
        $account->setListOptions($data->replyToList, $data->strictList);

        $this->accountRepository->save($account);

        return $account;
    }

    /**
     * Creates a new send-only address.
     *
     * @param SendOnlyData $data The validated form data.
     *
     * @throws LogicException if username, domain or password are missing.
     * @throws ValidationFailedException if the new account is not valid.
     */
    public function createSendOnly(SendOnlyData $data): Account
    {
        return $this->saveNew($data->createAccount(AccountType::SendOnly), $data);
    }

    /**
     * Deletes an account.
     *
     * The account is removed from all manager roles and its address from all mailing lists first. Aliases forwarding
     * to the address, and catch-all mailing lists having it as their last member, are deleted as well (and so on, for
     * aliases and lists forwarding to those), so no address keeps forwarding to the deleted one. Otherwise, whoever
     * gets the address next would receive their emails. Note that the mail storage of a mailbox is not deleted.
     *
     * @param Account $account The account to delete.
     *
     * @return list<array{address: string, destination: string}> The aliases and catch-all lists deleted as well, each
     *                                                             with the deleted destination that caused it.
     */
    public function delete(Account $account): array
    {
        $dependents = [];
        $deleted = [];

        $this->deleteWithDependents($account, $dependents, $deleted);

        return $dependents;
    }

    /**
     * Deletes an account and, recursively, the aliases and catch-all lists depending on it (see delete()).
     *
     * @param Account $account The account to delete.
     * @param list<array{address: string, destination: string}> $dependents The dependent accounts deleted so far.
     * @param array<int, true> $deleted The object IDs of all accounts deleted so far (to stop at circular forwards).
     */
    private function deleteWithDependents(Account $account, array &$dependents, array &$deleted): void
    {
        $address = (string) $account;
        $deleted[spl_object_id($account)] = true;

        $this->membershipManager->revokeManagerRoles($account);
        $orphans = [
            ...$this->membershipManager->removeFromAllLists($account),
            ...$this->accountRepository->findAliasesByDestination($account)
        ];

        $this->accountRepository->remove($account);

        foreach ($orphans as $orphan) {
            // The orphan may already be gone, e.g. with aliases forwarding to each other.
            if (isset($deleted[spl_object_id($orphan)])) {
                continue;
            }

            $dependents[] = ['address' => (string) $orphan, 'destination' => $address];
            $this->deleteWithDependents($orphan, $dependents, $deleted);
        }
    }

    /**
     * Enables or disables an account.
     *
     * Disabling only suspends the account: its manager roles are kept, but have no effect while it is disabled (see
     * Account::canBeManager()), and come into effect again once it is re-enabled.
     *
     * @param Account $account The account.
     * @param bool $enabled Whether the account should be enabled.
     *
     * @throws ValidationFailedException if the account is not valid afterwards.
     */
    public function setEnabled(Account $account, bool $enabled): void
    {
        if ($enabled) {
            $account->enable();
        } else {
            $account->disable();
        }

        $this->accountRepository->save($account);
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

        $data->applyTo($account);
        $account->makeAlias($this->destinationRepo->getEntity($destination));

        $this->accountRepository->save($account);
    }

    /**
     * Updates an existing mailbox.
     *
     * If the mailbox gets disabled, its manager roles are suspended, but kept (see setEnabled()).
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

        $data->applyTo($account);
        $account->setQuota($data->quota ?? Account::DEFAULT_QUOTA);
        $this->changePassword($account, $data->plainPassword);

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
        $data->applyTo($account);
        $account->setListOptions($data->replyToList, $data->strictList);

        $this->accountRepository->save($account);
    }

    /**
     * Updates an existing send-only address.
     *
     * @param Account $account The send-only address.
     * @param SendOnlyData $data The validated form data. An empty password leaves the password unchanged.
     *
     * @throws LogicException if the account is not a send-only address.
     * @throws ValidationFailedException if the account is not valid afterwards.
     */
    public function updateSendOnly(Account $account, SendOnlyData $data): void
    {
        if (!$account->isSendOnly()) {
            throw new LogicException('Only send-only addresses can be updated with send-only data.');
        }

        $data->applyTo($account);
        $this->changePassword($account, $data->plainPassword);

        $this->accountRepository->save($account);
    }

    /**
     * Sets a new password, if one is given.
     *
     * @param Account $account The account.
     * @param ?string $plainPassword The new password, or NULL/empty to leave it unchanged.
     */
    private function changePassword(Account $account, ?string $plainPassword): void
    {
        if ($plainPassword !== null && $plainPassword !== '') {
            $account->setPassword($this->hasher->hashPassword($account, $plainPassword));
        }
    }

    /**
     * Sets the password of a new account and saves it.
     *
     * @param Account $account The new account.
     * @param SendOnlyData $data The validated form data (with the password).
     *
     * @throws LogicException if the password is missing.
     * @throws ValidationFailedException if the new account is not valid.
     */
    private function saveNew(Account $account, SendOnlyData $data): Account
    {
        if ($data->plainPassword === null || $data->plainPassword === '') {
            throw new LogicException('A new account requires a password.');
        }

        $this->changePassword($account, $data->plainPassword);
        $this->accountRepository->save($account);

        return $account;
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
