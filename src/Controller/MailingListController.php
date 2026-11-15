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
use App\Form\Model\MailingListData;
use App\Security\Voter\AccountVoter;
use App\Security\Voter\DomainVoter;
use App\Service\AccountManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Controller for a mailing list's page: settings and deleting the list.
 *
 * Members and list managers are handled by ListMembershipController.
 */
#[IsGranted('IS_AUTHENTICATED')]
final class MailingListController extends AbstractListController
{
    /**
     * Shows the mailing list and handles its settings form.
     *
     * @param Account $list The mailing list.
     * @param Request $request The current request.
     * @param AccountManager $accountManager The service for managing accounts.
     */
    #[IsGranted(AccountVoter::LIST_MANAGE, subject: 'list')]
    #[Route('/manage/list/{id}', name: 'manage_list', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(#[MapEntity(id: 'id')] Account $list, Request $request, AccountManager $accountManager): Response
    {
        $data = MailingListData::fromAccount($list);
        $form = $this->listForms->settings($list, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $accountManager->updateMailingList($list, $data);
            $this->addFlash('success', new TranslatableMessage('listSaved', ['%list%' => (string) $list]));

            return $this->redirectToRoute('manage_list', ['id' => $list->id], Response::HTTP_SEE_OTHER);
        }

        return $this->renderListPage($list, settings: $form);
    }

    /**
     * Deletes the mailing list.
     *
     * @param Account $list The mailing list.
     * @param Request $request The current request.
     * @param AccountManager $accountManager The service for managing accounts.
     */
    #[IsGranted(AccountVoter::LIST_MANAGE, subject: 'list')]
    #[Route('/manage/list/{id}/delete', name: 'manage_list_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(#[MapEntity(id: 'id')] Account $list, Request $request, AccountManager $accountManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-list-' . $list->id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $domain = $list->domain;
        $address = (string) $list;

        $accountManager->delete($list);

        $this->addFlash('success', new TranslatableMessage('listDeleted', ['%list%' => $address]));

        // List managers without access to the domain go back to the overview.
        return $this->isGranted(DomainVoter::MANAGE, $domain)
            ? $this->redirectToRoute('manage_domain', ['domain' => $domain->domain], Response::HTTP_SEE_OTHER)
            : $this->redirectToRoute('manage', [], Response::HTTP_SEE_OTHER);
    }
}
