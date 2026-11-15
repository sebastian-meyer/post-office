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

use App\Entity\Domain;
use App\Form\Model\ManagerData;
use App\Form\Type\ManagerType;
use App\Repository\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for controllers of the domain page.
 */
abstract class AbstractDomainController extends AbstractController
{
    /**
     * Creates the form for appointing a domain manager.
     *
     * @param Domain $domain The domain.
     *
     * @return FormInterface<ManagerData> The form.
     */
    protected function createManagerForm(Domain $domain): FormInterface
    {
        return $this->createForm(ManagerType::class, new ManagerData($domain), [
            'action' => $this->generateUrl('manage_domain_manager_add', ['domain' => $domain->domain])
        ]);
    }

    /**
     * Renders the domain page.
     *
     * @param Domain $domain The domain.
     * @param ?FormInterface<ManagerData> $managerForm A submitted manager form to show with its errors (status 422).
     */
    protected function renderDomainPage(Domain $domain, ?FormInterface $managerForm = null): Response
    {
        return $this->render('pages/manage/domain.html.twig', [
            'domain' => $domain,
            'accounts' => $this->accountRepository->findBy(['domain' => $domain], ['username' => 'ASC']),
            'managerForm' => $managerForm ?? $this->createManagerForm($domain)
        ], new Response(status: ($managerForm?->isSubmitted() ?? false) ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @param AccountRepository $accountRepository The repository for accounts.
     */
    public function __construct(
        protected readonly AccountRepository $accountRepository
    ) {
    }
}
