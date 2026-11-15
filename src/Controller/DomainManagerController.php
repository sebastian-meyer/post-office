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
use App\Security\Voter\DomainVoter;
use App\Service\MembershipManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Controller for appointing and removing domain managers (administrators only, see DomainVoter).
 */
#[IsGranted('IS_AUTHENTICATED')]
final class DomainManagerController extends AbstractDomainController
{
    /**
     * Appoints a domain manager.
     *
     * @param Domain $domain The domain.
     * @param Request $request The current request.
     * @param MembershipManager $membershipManager The service for memberships and manager roles.
     */
    #[IsGranted(DomainVoter::MANAGE_MANAGERS, subject: 'domain')]
    #[Route('/manage/domain/{domain}/managers', name: 'manage_domain_manager_add', requirements: ['domain' => '[^/]+'], methods: ['POST'])]
    public function add(#[MapEntity(id: 'domain')] Domain $domain, Request $request, MembershipManager $membershipManager): Response
    {
        $form = $this->createManagerForm($domain);
        $form->handleRequest($request);
        $email = $form->getData()?->email;

        if (!$form->isSubmitted() || !$form->isValid() || $email === null) {
            return $this->renderDomainPage($domain, $form);
        }

        $membershipManager->addDomainManager($domain, $email);
        $this->addFlash('success', new TranslatableMessage('domainManagerAdded', ['%email%' => $email, '%domain%' => (string) $domain]));

        return $this->redirectToRoute('manage_domain', ['domain' => $domain->domain], Response::HTTP_SEE_OTHER);
    }

    /**
     * Removes a domain manager.
     *
     * @param Domain $domain The domain.
     * @param Account $manager The manager to remove.
     * @param Request $request The current request.
     * @param MembershipManager $membershipManager The service for memberships and manager roles.
     */
    #[IsGranted(DomainVoter::MANAGE_MANAGERS, subject: 'domain')]
    #[Route('/manage/domain/{domain}/managers/{manager}/remove', name: 'manage_domain_manager_remove', requirements: ['domain' => '[^/]+', 'manager' => '\d+'], methods: ['POST'])]
    public function remove(
        #[MapEntity(id: 'domain')] Domain $domain,
        #[MapEntity(id: 'manager')] Account $manager,
        Request $request,
        MembershipManager $membershipManager
    ): Response {
        if (!$this->isCsrfTokenValid('remove-domain-manager-' . $domain->domain, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $membershipManager->removeDomainManager($domain, $manager);
        $this->addFlash('success', new TranslatableMessage('domainManagerRemoved', ['%email%' => (string) $manager, '%domain%' => (string) $domain]));

        return $this->redirectToRoute('manage_domain', ['domain' => $domain->domain], Response::HTTP_SEE_OTHER);
    }
}
