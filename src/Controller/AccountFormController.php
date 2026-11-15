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
use App\Form\Model\AccountData;
use App\Form\Model\AliasData;
use App\Form\Model\MailboxData;
use App\Form\Model\MailingListData;
use App\Form\Model\SendOnlyData;
use App\Form\Type\AliasType;
use App\Form\Type\MailboxType;
use App\Form\Type\MailingListType;
use App\Form\Type\SendOnlyType;
use App\Security\Voter\AccountVoter;
use App\Security\Voter\DomainVoter;
use App\Service\AccountManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Controller for creating accounts of all types, and for editing mailboxes, aliases and send-only addresses.
 *
 * Existing mailing lists have their own page (see MailingListController).
 */
#[IsGranted('IS_AUTHENTICATED')]
final class AccountFormController extends AbstractController
{
    /**
     * The form types for the form data classes.
     */
    private const array FORM_TYPES = [
        MailboxData::class => MailboxType::class,
        SendOnlyData::class => SendOnlyType::class,
        AliasData::class => AliasType::class,
        MailingListData::class => MailingListType::class
    ];

    /**
     * Creates a new mailbox, alias, mailing list or send-only address in the domain.
     *
     * @param Domain $domain The domain.
     * @param string $type The account type ("mailbox", "alias", "list" or "sendonly").
     * @param Request $request The current request.
     */
    #[IsGranted(DomainVoter::MANAGE, subject: 'domain')]
    #[Route('/manage/domain/{domain}/new/{type}', name: 'manage_account_new', requirements: ['domain' => '[^/]+', 'type' => 'mailbox|alias|list|sendonly'], methods: ['GET', 'POST'])]
    public function new(#[MapEntity(id: 'domain')] Domain $domain, string $type, Request $request): Response
    {
        $data = match ($type) {
            'mailbox' => new MailboxData(),
            'alias' => new AliasData(),
            'sendonly' => new SendOnlyData(),
            default => new MailingListData()
        };
        $data->domain = $domain;

        $form = $this->createForm(self::FORM_TYPES[$data::class] ?? throw $this->createNotFoundException('Unknown account type.'), $data, [
            'is_new' => true,
            'domains' => [$domain]
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $account = match (true) {
                $data instanceof MailboxData => $this->accountManager->createMailbox($data),
                $data instanceof SendOnlyData => $this->accountManager->createSendOnly($data),
                $data instanceof AliasData => $this->accountManager->createAlias($data),
                default => $this->accountManager->createMailingList($data)
            };
            $this->addFlash('success', new TranslatableMessage('accountCreated', ['%address%' => (string) $account]));

            // New mailing lists have no members yet, so continue on the list's page.
            return $account->isMailingList()
                ? $this->redirectToRoute('manage_list', ['id' => $account->id], Response::HTTP_SEE_OTHER)
                : $this->redirectToRoute('manage_domain', ['domain' => $domain->domain], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm($form, $domain, null);
    }

    /**
     * Edits a mailbox, alias or send-only address.
     *
     * @param Account $account The account to edit.
     * @param Account $user The current user.
     * @param Request $request The current request.
     */
    #[IsGranted(AccountVoter::MANAGE, subject: 'account')]
    #[Route('/manage/account/{id}', name: 'manage_account_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(#[MapEntity(id: 'id')] Account $account, #[CurrentUser] Account $user, Request $request): Response
    {
        $data = match (true) {
            $account->isMailbox() => MailboxData::fromAccount($account),
            $account->isAlias() => AliasData::fromAccount($account),
            $account->isSendOnly() => SendOnlyData::fromAccount($account),
            default => throw $this->createNotFoundException('Mailing lists are edited on their own page.')
        };

        $form = $this->createForm(self::FORM_TYPES[$data::class] ?? throw $this->createNotFoundException('Unknown account type.'), $data, [
            // Prevent users from locking themselves out.
            'allow_disable' => $account !== $user
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($data instanceof MailboxData) {
                $this->accountManager->updateMailbox($account, $data);
            } elseif ($data instanceof SendOnlyData) {
                $this->accountManager->updateSendOnly($account, $data);
            } else {
                $this->accountManager->updateAlias($account, $data);
            }

            $this->addFlash('success', new TranslatableMessage('accountSaved', ['%address%' => (string) $account]));

            return $this->redirectToRoute('manage_domain', ['domain' => $account->domain->domain], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm($form, $account->domain, $account);
    }

    /**
     * Renders the form page.
     *
     * @param FormInterface<AccountData> $form The form.
     * @param Domain $domain The domain.
     * @param ?Account $account The edited account, or NULL for a new one.
     */
    private function renderForm(FormInterface $form, Domain $domain, ?Account $account): Response
    {
        $data = $form->getData();

        return $this->render('pages/manage/account_form.html.twig', [
            'form' => $form,
            'domain' => $domain,
            'account' => $account,
            'newHeading' => match (true) {
                $data instanceof MailboxData => 'newMailbox',
                $data instanceof SendOnlyData => 'newSendOnly',
                $data instanceof AliasData => 'newAlias',
                default => 'newMailingList'
            }
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @param AccountManager $accountManager The service for managing accounts.
     */
    public function __construct(
        private readonly AccountManager $accountManager
    ) {
    }
}
