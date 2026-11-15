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

use App\Entity\Blacklist;
use App\Form\Model\BlacklistData;
use App\Form\Type\BlacklistType;
use App\Repository\BlacklistRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for the blacklist of senders (administrators only).
 */
#[IsGranted('ROLE_ADMIN')]
final class BlacklistController extends AbstractController
{
    /**
     * Lists all blacklist entries.
     *
     * @return array<string, mixed> The template variables.
     */
    #[Route('/admin/blacklist', name: 'admin_blacklist', methods: ['GET'])]
    #[Template('pages/admin/blacklist.html.twig')]
    public function index(): array
    {
        return [
            'entries' => $this->blacklistRepository->findBy([], ['domain' => 'ASC', 'username' => 'ASC'])
        ];
    }

    /**
     * Creates a new blacklist entry.
     *
     * @param Request $request The current request.
     * @param TranslatorInterface $translator The translator (for the error message of duplicate entries).
     */
    #[Route('/admin/blacklist/new', name: 'admin_blacklist_new', methods: ['GET', 'POST'])]
    public function new(Request $request, TranslatorInterface $translator): Response
    {
        $data = new BlacklistData();
        $form = $this->createForm(BlacklistType::class, $data, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $data->address !== null) {
            $entry = new Blacklist(strtolower($data->address));

            // The unique index doesn't prevent duplicate domain entries (with a NULL username), so check explicitly.
            if ($this->blacklistRepository->findOneBy(['username' => $entry->username, 'domain' => $entry->domain]) === null) {
                return $this->save($entry, $data);
            }

            $form->get('address')->addError(new FormError($translator->trans('emailAlreadyExists', [], 'validators')));
        }

        return $this->renderForm($form, 'newBlacklistEntry');
    }

    /**
     * Edits a blacklist entry.
     *
     * @param Blacklist $entry The entry.
     * @param Request $request The current request.
     */
    #[Route('/admin/blacklist/{id}/edit', name: 'admin_blacklist_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(#[MapEntity(id: 'id')] Blacklist $entry, Request $request): Response
    {
        $data = BlacklistData::fromBlacklist($entry);
        $form = $this->createForm(BlacklistType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->save($entry, $data);
        }

        return $this->renderForm($form, 'editBlacklistEntry', self::address($entry));
    }

    /**
     * Deletes a blacklist entry.
     *
     * @param Blacklist $entry The entry.
     * @param Request $request The current request.
     */
    #[Route('/admin/blacklist/{id}/delete', name: 'admin_blacklist_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(#[MapEntity(id: 'id')] Blacklist $entry, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-blacklist-' . $entry->id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $address = self::address($entry);
        $this->blacklistRepository->remove($entry);
        $this->addFlash('success', new TranslatableMessage('blacklistEntryDeleted', ['%address%' => $address]));

        return $this->redirectToRoute('admin_blacklist', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Returns the blacklisted address (email address or domain) of an entry.
     *
     * @param Blacklist $entry The entry.
     */
    private static function address(Blacklist $entry): string
    {
        return $entry->username !== null ? $entry->username . '@' . $entry->domain : $entry->domain;
    }

    /**
     * Applies the form data and saves the entry.
     *
     * @param Blacklist $entry The entry.
     * @param BlacklistData $data The validated form data.
     */
    private function save(Blacklist $entry, BlacklistData $data): Response
    {
        $entry->setAction($data->action ?? 'REJECT');
        $data->enabled ? $entry->enable() : $entry->disable();

        $this->blacklistRepository->save($entry);
        $this->addFlash('success', new TranslatableMessage('blacklistEntrySaved', ['%address%' => self::address($entry)]));

        return $this->redirectToRoute('admin_blacklist', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Renders the form page.
     *
     * @param FormInterface<BlacklistData> $form The form.
     * @param string $heading The heading (translation key).
     * @param ?string $address The address of an edited entry (translation parameter of the heading).
     */
    private function renderForm(FormInterface $form, string $heading, ?string $address = null): Response
    {
        return $this->render('pages/admin/form.html.twig', [
            'form' => $form,
            'heading' => $heading,
            'headingParameters' => ['%address%' => $address],
            'back' => $this->generateUrl('admin_blacklist')
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @param BlacklistRepository $blacklistRepository The repository for blacklist entries.
     */
    public function __construct(
        private readonly BlacklistRepository $blacklistRepository
    ) {
    }
}
