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
use App\Repository\AccountRepository;
use App\Security\Voter\AccountVoter;
use App\Service\MembershipManager;
use LogicException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Controller for the user's account settings and mailing list memberships.
 */
final class AccountController extends AbstractController
{
    /**
     * Shows the user's account settings and mailing list memberships.
     *
     * @param Account $account The current user.
     * @param AccountRepository $accountRepository The repository for accounts.
     *
     * @return array<string, mixed> The template variables.
     */
    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/account', name: 'account', methods: ['GET'])]
    #[Template('pages/account.html.twig')]
    public function index(#[CurrentUser] Account $account, AccountRepository $accountRepository): array
    {
        return [
            'account' => $account,
            'memberships' => $accountRepository->findListsByMember($account)
        ];
    }

    /**
     * Removes the current user from a mailing list.
     *
     * @param Account $list The mailing list.
     * @param Account $account The current user.
     * @param Request $request The current request.
     * @param MembershipManager $membershipManager The service for list memberships.
     */
    #[IsGranted(AccountVoter::LIST_LEAVE, subject: 'list')]
    #[Route('/account/lists/{id}/leave', name: 'account_leave_list', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function leaveList(
        #[MapEntity(id: 'id')] Account $list,
        #[CurrentUser] Account $account,
        Request $request,
        MembershipManager $membershipManager
    ): Response {
        if (!$this->isCsrfTokenValid('leave-list-' . $list->id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $membershipManager->removeMember($list, $account->getUserIdentifier());
            $this->addFlash('success', new TranslatableMessage('listLeft', ['%list%' => $list->getUserIdentifier()]));
        } catch (LogicException) {
            // e.g. the user is the only member of a catch-all list
            $this->addFlash('danger', new TranslatableMessage('listLeaveFailed', ['%list%' => $list->getUserIdentifier()]));
        }

        return $this->redirectToRoute('account', [], Response::HTTP_SEE_OTHER);
    }
}
