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

namespace App\Controller\Admin;

use App\Entity\Domain;
use App\Form\Model\DomainData;
use App\Form\Type\DomainType;
use App\Repository\AccountRepository;
use App\Repository\DomainRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Controller for creating and deleting domains (administrators only).
 *
 * Domains are listed on the management overview (see ManageController).
 */
#[IsGranted('ROLE_ADMIN')]
final class DomainController extends AbstractController
{
    /**
     * Creates a new domain.
     *
     * @param Request $request The current request.
     */
    #[Route('/admin/domains/new', name: 'admin_domain_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $data = new DomainData();
        $form = $this->createForm(DomainType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $data->domain !== null) {
            $domain = new Domain(strtolower(trim($data->domain)));
            $this->domainRepository->save($domain);
            $this->addFlash('success', new TranslatableMessage('domainCreated', ['%domain%' => (string) $domain]));

            return $this->redirectToRoute('manage_domain', ['domain' => $domain->domain], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pages/admin/form.html.twig', [
            'form' => $form,
            'heading' => 'newDomain',
            'back' => $this->generateUrl('manage')
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Deletes a domain.
     *
     * Only domains without accounts can be deleted, so no accounts (e.g. of administrators) get lost by accident.
     *
     * @param Domain $domain The domain.
     * @param Request $request The current request.
     */
    #[Route('/admin/domains/{domain}/delete', name: 'admin_domain_delete', requirements: ['domain' => '[^/]+'], methods: ['POST'])]
    public function delete(#[MapEntity(id: 'domain')] Domain $domain, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-domain-' . $domain->domain, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($this->accountRepository->count(['domain' => $domain]) > 0) {
            $this->addFlash('danger', new TranslatableMessage('domainNotEmpty', ['%domain%' => (string) $domain]));
        } else {
            $this->domainRepository->remove($domain);
            $this->addFlash('success', new TranslatableMessage('domainDeleted', ['%domain%' => (string) $domain]));
        }

        return $this->redirectToRoute('manage', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @param DomainRepository $domainRepository The repository for domains.
     * @param AccountRepository $accountRepository The repository for accounts.
     */
    public function __construct(
        private readonly DomainRepository $domainRepository,
        private readonly AccountRepository $accountRepository
    ) {
    }
}
