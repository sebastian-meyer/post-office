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

use App\Entity\TlsPolicy;
use App\Form\Model\TlsPolicyData;
use App\Form\Type\TlsPolicyType;
use App\Repository\TlsPolicyRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Controller for the TLS policies of recipient domains (administrators only).
 *
 * The domain is passed as query or form parameter instead of a path segment, since domains can start with a dot,
 * and web servers usually block paths containing "/." (hidden files).
 */
#[IsGranted('ROLE_ADMIN')]
final class TlsPolicyController extends AbstractController
{
    /**
     * Lists all TLS policies.
     *
     * @return array<string, mixed> The template variables.
     */
    #[Route('/admin/tls-policies', name: 'admin_tls_policies', methods: ['GET'])]
    #[Template('pages/admin/tls_policies.html.twig')]
    public function index(): array
    {
        return [
            'policies' => $this->tlsPolicyRepository->findBy([], ['domain' => 'ASC'])
        ];
    }

    /**
     * Creates a new TLS policy.
     *
     * @param Request $request The current request.
     */
    #[Route('/admin/tls-policies/new', name: 'admin_tls_policy_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $data = new TlsPolicyData();
        $form = $this->createForm(TlsPolicyType::class, $data, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $data->domain !== null) {
            $tlsPolicy = new TlsPolicy(strtolower(trim($data->domain)));

            return $this->save($tlsPolicy, $data);
        }

        return $this->renderForm($form, 'newTlsPolicy');
    }

    /**
     * Edits a TLS policy.
     *
     * @param string $domain The domain of the policy.
     * @param Request $request The current request.
     */
    #[Route('/admin/tls-policies/edit', name: 'admin_tls_policy_edit', methods: ['GET', 'POST'])]
    public function edit(#[MapQueryParameter] string $domain, Request $request): Response
    {
        $tlsPolicy = $this->findTlsPolicy($domain);
        $data = TlsPolicyData::fromTlsPolicy($tlsPolicy);
        $form = $this->createForm(TlsPolicyType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->save($tlsPolicy, $data);
        }

        return $this->renderForm($form, 'editTlsPolicy', $tlsPolicy->domain);
    }

    /**
     * Deletes a TLS policy.
     *
     * @param Request $request The current request (with the domain of the policy).
     */
    #[Route('/admin/tls-policies/delete', name: 'admin_tls_policy_delete', methods: ['POST'])]
    public function delete(Request $request): Response
    {
        $tlsPolicy = $this->findTlsPolicy($request->getPayload()->getString('domain'));

        if (!$this->isCsrfTokenValid('delete-tls-policy-' . $tlsPolicy->domain, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->tlsPolicyRepository->remove($tlsPolicy);
        $this->addFlash('success', new TranslatableMessage('tlsPolicyDeleted', ['%domain%' => $tlsPolicy->domain]));

        return $this->redirectToRoute('admin_tls_policies', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Enables or disables a TLS policy (with a click on its status in the table).
     *
     * @param Request $request The current request (with the domain of the policy).
     */
    #[Route('/admin/tls-policies/toggle', name: 'admin_tls_policy_toggle', methods: ['POST'])]
    public function toggle(Request $request): Response
    {
        $tlsPolicy = $this->findTlsPolicy($request->getPayload()->getString('domain'));

        if (!$this->isCsrfTokenValid('toggle-tls-policy-' . $tlsPolicy->domain, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $tlsPolicy->enabled ? $tlsPolicy->disable() : $tlsPolicy->enable();
        $this->tlsPolicyRepository->save($tlsPolicy);
        $this->addFlash('success', new TranslatableMessage($tlsPolicy->enabled ? 'entryEnabled' : 'entryDisabled', ['%name%' => $tlsPolicy->domain]));

        return $this->redirectToRoute('admin_tls_policies', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Finds the policy for the domain.
     *
     * @param string $domain The domain.
     *
     * @throws NotFoundHttpException if there is no policy for the domain.
     */
    private function findTlsPolicy(string $domain): TlsPolicy
    {
        return $this->tlsPolicyRepository->find($domain) ?? throw $this->createNotFoundException('TLS policy not found.');
    }

    /**
     * Applies the form data and saves the policy.
     *
     * @param TlsPolicy $tlsPolicy The policy.
     * @param TlsPolicyData $data The validated form data.
     */
    private function save(TlsPolicy $tlsPolicy, TlsPolicyData $data): Response
    {
        $tlsPolicy->setPolicy($data->policy ?? 'none')->setParams($data->params ?? '');
        $data->enabled ? $tlsPolicy->enable() : $tlsPolicy->disable();

        $this->tlsPolicyRepository->save($tlsPolicy);
        $this->addFlash('success', new TranslatableMessage('tlsPolicySaved', ['%domain%' => $tlsPolicy->domain]));

        return $this->redirectToRoute('admin_tls_policies', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Renders the form page.
     *
     * @param FormInterface<TlsPolicyData> $form The form.
     * @param string $heading The heading (translation key).
     * @param ?string $domain The domain of an edited policy (translation parameter of the heading).
     */
    private function renderForm(FormInterface $form, string $heading, ?string $domain = null): Response
    {
        return $this->render('pages/admin/form.html.twig', [
            'form' => $form,
            'heading' => $heading,
            'headingParameters' => ['%domain%' => $domain],
            'back' => $this->generateUrl('admin_tls_policies')
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @param TlsPolicyRepository $tlsPolicyRepository The repository for TLS policies.
     */
    public function __construct(
        private readonly TlsPolicyRepository $tlsPolicyRepository
    ) {
    }
}
