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

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Domain;
use App\Repository\AccountRepository;
use App\Security\Voter\AccountVoter;
use App\Security\Voter\DomainVoter;
use App\Service\AccountManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Controller for actions on the accounts marked in the domain's account table (enable, disable, delete).
 *
 * Each account is checked like for the single actions (see ManageController); accounts the user may not change are
 * skipped and reported.
 */
#[IsGranted('IS_AUTHENTICATED')]
final class BulkAccountController extends AbstractController
{
    /**
     * Enables or disables the marked accounts.
     *
     * @param Domain $domain The domain.
     * @param string $action "enable" or "disable".
     * @param Account $user The current user.
     * @param Request $request The current request (with the marked account IDs).
     */
    #[IsGranted(DomainVoter::MANAGE, subject: 'domain')]
    #[Route('/manage/domain/{domain}/accounts/{action}', name: 'manage_domain_bulk_toggle', requirements: ['domain' => '[^/]+', 'action' => 'enable|disable'], methods: ['POST'])]
    public function toggle(#[MapEntity(id: 'domain')] Domain $domain, string $action, #[CurrentUser] Account $user, Request $request): Response
    {
        $enable = $action === 'enable';
        $changed = [];
        $skipped = [];

        foreach ($this->getSelectedAccounts($domain, $request) as $account) {
            // Prevent users from locking themselves out.
            if (!$this->isGranted(AccountVoter::MANAGE, $account) || (!$enable && $account === $user)) {
                $skipped[] = (string) $account;
            } elseif ($account->enabled !== $enable) {
                $this->accountManager->setEnabled($account, $enable);
                $changed[] = (string) $account;
            }
        }

        if ($changed !== []) {
            $this->addFlash('success', new TranslatableMessage($enable ? 'accountsEnabled' : 'accountsDisabled', ['%count%' => \count($changed), '%addresses%' => implode(', ', $changed)]));
        }

        return $this->redirectToDomain($domain, $skipped);
    }

    /**
     * Deletes the marked accounts.
     *
     * Aliases and catch-all lists depending on a deleted account are deleted as well (see AccountManager::delete()).
     *
     * @param Domain $domain The domain.
     * @param Request $request The current request (with the marked account IDs).
     */
    #[IsGranted(DomainVoter::MANAGE, subject: 'domain')]
    #[Route('/manage/domain/{domain}/accounts/delete', name: 'manage_domain_bulk_delete', requirements: ['domain' => '[^/]+'], methods: ['POST'])]
    public function delete(#[MapEntity(id: 'domain')] Domain $domain, Request $request): Response
    {
        $deleted = [];
        $skipped = [];
        $dependents = [];

        foreach ($this->getSelectedAccounts($domain, $request) as $account) {
            // The account may already be gone as a dependent of another deleted account.
            if (!$this->entityManager->contains($account)) {
                continue;
            }

            if (!$this->isGranted(AccountVoter::DELETE, $account)) {
                $skipped[] = (string) $account;
                continue;
            }

            $deleted[] = (string) $account;
            $dependents = [...$dependents, ...$this->accountManager->delete($account)];
        }

        if ($deleted !== []) {
            $this->addFlash('success', new TranslatableMessage('accountsDeleted', ['%count%' => \count($deleted), '%addresses%' => implode(', ', $deleted)]));
        }

        foreach ($dependents as $dependent) {
            $this->addFlash('warning', new TranslatableMessage('dependentAccountDeleted', ['%address%' => $dependent['address'], '%destination%' => $dependent['destination']]));
        }

        return $this->redirectToDomain($domain, $skipped);
    }

    /**
     * Returns the marked accounts of the domain, after checking the CSRF token.
     *
     * @param Domain $domain The domain.
     * @param Request $request The current request.
     *
     * @return list<Account> The accounts (IDs of other domains are ignored).
     */
    private function getSelectedAccounts(Domain $domain, Request $request): array
    {
        $payload = $request->getPayload();

        if (!$this->isCsrfTokenValid('bulk-accounts-' . $domain->domain, $payload->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $ids = array_values(array_map(intval(...), array_filter($payload->all('selection'), is_numeric(...))));

        if ($ids === []) {
            $this->addFlash('warning', new TranslatableMessage('nothingMarked'));

            return [];
        }

        return $this->accountRepository->findBy(['id' => $ids, 'domain' => $domain], ['username' => 'ASC']);
    }

    /**
     * Reports the skipped accounts and redirects to the domain page.
     *
     * @param Domain $domain The domain.
     * @param list<string> $skipped The addresses of the accounts the user may not change.
     */
    private function redirectToDomain(Domain $domain, array $skipped): Response
    {
        if ($skipped !== []) {
            $this->addFlash('warning', new TranslatableMessage('accountsSkipped', ['%count%' => \count($skipped), '%addresses%' => implode(', ', $skipped)]));
        }

        return $this->redirectToRoute('manage_domain', ['domain' => $domain->domain], Response::HTTP_SEE_OTHER);
    }

    /**
     * @param AccountRepository $accountRepository The repository for accounts.
     * @param AccountManager $accountManager The service for managing accounts.
     * @param EntityManagerInterface $entityManager The entity manager (to check whether an account still exists).
     */
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly AccountManager $accountManager,
        private readonly EntityManagerInterface $entityManager
    ) {
    }
}
