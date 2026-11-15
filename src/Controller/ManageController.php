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
use App\Security\Voter\AccountVoter;
use App\Security\Voter\DomainVoter;
use App\Service\AccountManager;
use App\Service\ManagementScope;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Controller for the management area: overview, domain pages and deleting accounts.
 */
#[IsGranted('IS_AUTHENTICATED')]
final class ManageController extends AbstractDomainController
{
    /**
     * Shows the domains and mailing lists the user can manage.
     *
     * @param Account $user The current user.
     * @param ManagementScope $managementScope The service determining what a user can manage.
     *
     * @return array<string, mixed> The template variables.
     */
    #[Route('/manage', name: 'manage', methods: ['GET'])]
    #[Template('pages/manage/index.html.twig')]
    public function index(#[CurrentUser] Account $user, ManagementScope $managementScope): array
    {
        if (!$managementScope->managesAnything($user)) {
            throw $this->createAccessDeniedException('The user cannot manage anything.');
        }

        $domains = [];

        foreach ($managementScope->getManagedDomains($user) as $domain) {
            $domains[] = ['domain' => $domain, 'accounts' => $this->accountRepository->count(['domain' => $domain])];
        }

        return [
            'domains' => $domains,
            'lists' => $managementScope->getManagedLists($user)
        ];
    }

    /**
     * Shows all accounts of a domain.
     *
     * @param Domain $domain The domain.
     */
    #[IsGranted(DomainVoter::MANAGE, subject: 'domain')]
    // The domain must not be the last path segment, since domains can look like file names: web servers block paths
    // ending in e.g. ".sh" (a TLD), and Turbo doesn't handle URLs ending in e.g. ".zip" (also a TLD).
    #[Route('/manage/domain/{domain}/accounts', name: 'manage_domain', requirements: ['domain' => '[^/]+'], methods: ['GET'])]
    public function domain(#[MapEntity(id: 'domain')] Domain $domain): Response
    {
        return $this->renderDomainPage($domain);
    }

    /**
     * Enables or disables an account (with a click on its status in the domain's account table).
     *
     * @param Account $account The account.
     * @param Account $user The current user.
     * @param Request $request The current request.
     * @param AccountManager $accountManager The service for managing accounts.
     */
    #[IsGranted(AccountVoter::MANAGE, subject: 'account')]
    #[Route('/manage/account/{id}/toggle', name: 'manage_account_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(
        #[MapEntity(id: 'id')] Account $account,
        #[CurrentUser] Account $user,
        Request $request,
        AccountManager $accountManager
    ): Response {
        if (!$this->isCsrfTokenValid('toggle-account-' . $account->id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // Prevent users from locking themselves out.
        if ($account === $user) {
            throw $this->createAccessDeniedException('Users cannot disable their own account.');
        }

        $accountManager->setEnabled($account, !$account->enabled);
        $this->addFlash('success', new TranslatableMessage($account->enabled ? 'entryEnabled' : 'entryDisabled', ['%name%' => (string) $account]));

        return $this->redirectToRoute('manage_domain', ['domain' => $account->domain->domain], Response::HTTP_SEE_OTHER);
    }

    /**
     * Deletes an account.
     *
     * The mail storage of a mailbox is not deleted (the user is warned about this before).
     *
     * @param Account $account The account to delete.
     * @param Request $request The current request.
     * @param AccountManager $accountManager The service for managing accounts.
     */
    #[IsGranted(AccountVoter::DELETE, subject: 'account')]
    #[Route('/manage/account/{id}/delete', name: 'manage_account_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(#[MapEntity(id: 'id')] Account $account, Request $request, AccountManager $accountManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-account-' . $account->id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $domain = $account->domain;
        $wasMailbox = $account->isMailbox();
        $address = (string) $account;

        $dependents = $accountManager->delete($account);

        $this->addFlash('success', new TranslatableMessage($wasMailbox ? 'mailboxDeleted' : 'accountDeleted', ['%address%' => $address]));

        foreach ($dependents as $dependent) {
            $this->addFlash('warning', new TranslatableMessage('dependentAccountDeleted', ['%address%' => $dependent['address'], '%destination%' => $dependent['destination']]));
        }

        return $this->redirectToRoute('manage_domain', ['domain' => $domain->domain], Response::HTTP_SEE_OTHER);
    }
}
