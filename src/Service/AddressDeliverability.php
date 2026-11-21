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
use App\Repository\AccountRepository;
use App\Repository\DomainRepository;

/**
 * Decides whether the mail server accepts an email from the sender to an arbitrary address (e.g. a list member).
 *
 * This follows the lookups of the Postfix configuration (see mailserver/postfix/): addresses of hosted domains are
 * delivered to the enabled account with that username (ignoring an address extension like "+news"), or else to the
 * domain's catch-all address; without both, they are rejected as unknown. Addresses of other domains are relayed,
 * so they can't be checked here.
 */
final class AddressDeliverability
{
    /**
     * Whether the mail server accepts emails from the sender to the address.
     *
     * @param string $address The email address.
     * @param Account $sender The sender (the current user).
     */
    public function accepts(string $address, Account $sender): bool
    {
        [$localPart, $hostname] = explode('@', strtolower(trim($address)), 2) + [1 => ''];
        $domain = $this->domainRepository->find($hostname);

        if ($domain === null) {
            return true;
        }

        $username = explode(AccountAddresses::DELIMITER, $localPart, 2)[0];
        $account = $username !== '' ? $this->accountRepository->findOneBy(['username' => $username, 'domain' => $domain]) : null;

        // An enabled account decides (e.g. send-only addresses are rejected even if there is a catch-all address).
        if ($account !== null && $account->enabled) {
            return $this->policy->canReceiveEmails($account, $sender);
        }

        $catchAll = $this->accountRepository->findOneBy(['username' => null, 'domain' => $domain]);

        return $catchAll !== null && $catchAll->enabled && !$catchAll->destinations->isEmpty();
    }

    /**
     * @param AccountRepository $accountRepository The repository for accounts.
     * @param DomainRepository $domainRepository The repository for domains.
     * @param RecipientPolicy $policy The rules for which accounts can receive emails.
     */
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly DomainRepository $domainRepository,
        private readonly RecipientPolicy $policy
    ) {
    }
}
