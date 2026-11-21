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
use App\Entity\Destination;
use App\Security\Voter\AccountVoter;
use App\Service\MembershipManager;
use LogicException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Controller for the members and list managers of a mailing list.
 */
#[IsGranted('IS_AUTHENTICATED')]
final class ListMembershipController extends AbstractListController
{
    /**
     * Adds a member to the mailing list.
     *
     * @param Account $list The mailing list.
     * @param Request $request The current request.
     * @param MembershipManager $membershipManager The service for memberships and manager roles.
     */
    #[IsGranted(AccountVoter::LIST_MANAGE, subject: 'list')]
    #[Route('/manage/list/{id}/members', name: 'manage_list_member_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addMember(#[MapEntity(id: 'id')] Account $list, Request $request, MembershipManager $membershipManager): Response
    {
        $form = $this->listForms->member($list);
        $form->handleRequest($request);
        $email = $form->getData()?->email;

        if (!$form->isSubmitted() || !$form->isValid() || $email === null) {
            return $this->renderListPage($list, member: $form);
        }

        $membershipManager->addMember($list, $email);
        $this->addFlash('success', new TranslatableMessage('memberAdded', ['%email%' => $email]));

        return $this->redirectToList($list);
    }

    /**
     * Removes a member from the mailing list.
     *
     * @param Account $list The mailing list.
     * @param Request $request The current request (with the member's email address).
     * @param MembershipManager $membershipManager The service for memberships and manager roles.
     */
    #[IsGranted(AccountVoter::LIST_MANAGE, subject: 'list')]
    #[Route('/manage/list/{id}/members/remove', name: 'manage_list_member_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function removeMember(#[MapEntity(id: 'id')] Account $list, Request $request, MembershipManager $membershipManager): Response
    {
        $this->checkToken('remove-member-' . $list->id, $request);
        $email = $request->getPayload()->getString('email');

        try {
            $membershipManager->removeMember($list, $email);
            $this->addFlash('success', new TranslatableMessage('memberRemoved', ['%email%' => $email]));
        } catch (LogicException) {
            // The last member of a catch-all list cannot be removed.
            $this->addFlash('danger', new TranslatableMessage('memberRemoveFailed', ['%email%' => $email]));
        }

        return $this->redirectToList($list);
    }

    /**
     * Removes the members marked in the list's member table.
     *
     * @param Account $list The mailing list.
     * @param Request $request The current request (with the marked email addresses).
     * @param MembershipManager $membershipManager The service for memberships and manager roles.
     */
    #[IsGranted(AccountVoter::LIST_MANAGE, subject: 'list')]
    #[Route('/manage/list/{id}/members/remove-marked', name: 'manage_list_members_bulk_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function removeMembers(#[MapEntity(id: 'id')] Account $list, Request $request, MembershipManager $membershipManager): Response
    {
        $this->checkToken('bulk-members-' . $list->id, $request);
        $members = array_map(static fn (Destination $member): string => strtolower((string) $member), $list->destinations->getValues());
        $emails = array_values(array_filter(
            $request->getPayload()->all('selection'),
            static fn (mixed $email): bool => \is_string($email) && \in_array(strtolower($email), $members, true)
        ));
        $removed = [];

        if ($emails === []) {
            $this->addFlash('warning', new TranslatableMessage('nothingMarked'));
        }

        foreach ($emails as $email) {
            try {
                $membershipManager->removeMember($list, $email);
                $removed[] = $email;
            } catch (LogicException) {
                // The last member of a catch-all list cannot be removed.
                $this->addFlash('danger', new TranslatableMessage('memberRemoveFailed', ['%email%' => $email]));
            }
        }

        if ($removed !== []) {
            $this->addFlash('success', new TranslatableMessage('membersRemoved', ['%count%' => \count($removed), '%emails%' => implode(', ', $removed)]));
        }

        return $this->redirectToList($list);
    }

    /**
     * Appoints a list manager.
     *
     * @param Account $list The mailing list.
     * @param Request $request The current request.
     * @param MembershipManager $membershipManager The service for memberships and manager roles.
     */
    #[IsGranted(AccountVoter::LIST_MANAGE_MANAGERS, subject: 'list')]
    #[Route('/manage/list/{id}/managers', name: 'manage_list_manager_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addManager(#[MapEntity(id: 'id')] Account $list, Request $request, MembershipManager $membershipManager): Response
    {
        $form = $this->listForms->manager($list);
        $form->handleRequest($request);
        $email = $form->getData()?->email;

        if (!$form->isSubmitted() || !$form->isValid() || $email === null) {
            return $this->renderListPage($list, manager: $form);
        }

        $membershipManager->addListManager($list, $email);
        $this->addFlash('success', new TranslatableMessage('managerAdded', ['%email%' => $email]));

        return $this->redirectToList($list);
    }

    /**
     * Removes a list manager.
     *
     * @param Account $list The mailing list.
     * @param Account $manager The manager to remove.
     * @param Request $request The current request.
     * @param MembershipManager $membershipManager The service for memberships and manager roles.
     */
    #[IsGranted(AccountVoter::LIST_MANAGE_MANAGERS, subject: 'list')]
    #[Route('/manage/list/{id}/managers/{manager}/remove', name: 'manage_list_manager_remove', requirements: ['id' => '\d+', 'manager' => '\d+'], methods: ['POST'])]
    public function removeManager(
        #[MapEntity(id: 'id')] Account $list,
        #[MapEntity(id: 'manager')] Account $manager,
        Request $request,
        MembershipManager $membershipManager
    ): Response {
        $this->checkToken('remove-manager-' . $list->id, $request);

        $membershipManager->removeListManager($list, $manager);
        $this->addFlash('success', new TranslatableMessage('managerRemoved', ['%email%' => (string) $manager]));

        return $this->redirectToList($list);
    }

    /**
     * Checks the CSRF token of a button form.
     *
     * @param string $tokenId The token ID.
     * @param Request $request The current request.
     */
    private function checkToken(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * Redirects to the mailing list page.
     *
     * @param Account $list The mailing list.
     */
    private function redirectToList(Account $list): Response
    {
        return $this->redirectToRoute('manage_list', ['id' => $list->id], Response::HTTP_SEE_OTHER);
    }
}
