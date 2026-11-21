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
use App\Form\Model\ComposeData;
use App\Form\Type\ComposeType;
use App\Repository\AccountRepository;
use App\Security\SubmissionCredentials;
use App\Security\Voter\AccountVoter;
use App\Security\Voter\DomainVoter;
use App\Service\BulkMailer;
use App\Service\MailDeliveryException;
use App\Service\RecipientPolicy;
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
 * Controller for writing emails to accounts of a domain, or to a mailing list and its members.
 *
 * The recipients are marked in the table of the domain or list page, which submits them as "selection" (account IDs
 * on the domain page, email addresses on the list page). Addresses the mail server would reject are skipped (see
 * RecipientPolicy). The email is sent with the user's own credentials (see BulkMailer).
 */
#[IsGranted('IS_AUTHENTICATED')]
final class ComposeController extends AbstractController
{
    /**
     * Writes an email to accounts of the domain (administrators and domain managers).
     *
     * @param Domain $domain The domain.
     * @param Account $user The current user.
     * @param Request $request The current request (with the selected account IDs).
     */
    #[IsGranted(DomainVoter::MANAGE, subject: 'domain')]
    #[Route('/manage/domain/{domain}/compose', name: 'manage_domain_compose', requirements: ['domain' => '[^/]+'], methods: ['GET', 'POST'])]
    public function domain(#[MapEntity(id: 'domain')] Domain $domain, #[CurrentUser] Account $user, Request $request): Response
    {
        $selection = $this->getSelection($request);
        $choices = [];
        $skipped = [];

        foreach ($this->accountRepository->findBy(['domain' => $domain], ['username' => 'ASC']) as $account) {
            if ($this->recipientPolicy->canReceiveEmails($account, $user)) {
                $choices[(string) $account] = (string) $account->id;
            } elseif (\in_array((string) $account->id, $selection, true)) {
                $skipped[] = (string) $account;
            }
        }

        return $this->compose($request, $user, $choices, $skipped, $this->generateUrl('manage_domain', ['domain' => $domain->domain]), [
            'domain' => $domain,
            'list' => null
        ]);
    }

    /**
     * Writes an email to the mailing list and/or its members (administrators, domain managers and list managers).
     *
     * @param Account $list The mailing list.
     * @param Account $user The current user.
     * @param Request $request The current request (with the selected email addresses).
     */
    #[IsGranted(AccountVoter::LIST_MANAGE, subject: 'list')]
    #[Route('/manage/list/{id}/compose', name: 'manage_list_compose', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function list(#[MapEntity(id: 'id')] Account $list, #[CurrentUser] Account $user, Request $request): Response
    {
        $selection = $this->getSelection($request);
        $members = array_map(strval(...), $list->destinations->getValues());
        $choices = [];
        $skipped = [];

        // Members which are local accounts the mail server would reject.
        $rejected = [];
        foreach ($this->accountRepository->findByEmails($members) as $account) {
            if (!$this->recipientPolicy->canReceiveEmails($account, $user)) {
                $rejected[strtolower((string) $account)] = true;
            }
        }

        if ($this->recipientPolicy->canReceiveEmails($list, $user)) {
            $choices[(string) $list] = (string) $list;
        } elseif (\in_array((string) $list, $selection, true)) {
            $skipped[] = (string) $list;
        }

        foreach ($members as $member) {
            if (!isset($rejected[strtolower($member)])) {
                $choices[$member] = $member;
            } elseif (\in_array($member, $selection, true)) {
                $skipped[] = $member;
            }
        }

        return $this->compose($request, $user, $choices, $skipped, $this->generateUrl('manage_list', ['id' => $list->id]), [
            'domain' => $list->domain,
            'list' => $list
        ]);
    }

    /**
     * Shows the form and sends the email.
     *
     * @param Request $request The current request.
     * @param Account $user The current user (the sender).
     * @param array<string, string> $choices The recipients the user may write to (email address => form value).
     * @param list<string> $skipped The selected addresses that cannot receive emails.
     * @param string $backUrl The URL of the domain or list page.
     * @param array<string, mixed> $context Additional template variables (domain and list).
     */
    private function compose(Request $request, Account $user, array $choices, array $skipped, string $backUrl, array $context): Response
    {
        $data = new ComposeData();
        $password = $this->credentials->get($user);
        $isSubmission = $request->request->has('compose');

        if (!$isSubmission) {
            $data->recipients = array_values(array_intersect($choices, $this->getSelection($request)));
        }

        $form = $this->createComposeForm($data, $choices, $password === null);

        if ($isSubmission) {
            $form->handleRequest($request);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $recipients = array_map(strval(...), array_keys(array_intersect($choices, $data->recipients)));

            try {
                $count = $this->mailer->send($user, $password ?? (string) $data->password, $recipients, (string) $data->subject, (string) $data->body);
            } catch (MailDeliveryException $exception) {
                if ($exception->delivered > 0) {
                    // Don't let the user send the email again to the recipients who already got it.
                    $this->addFlash('danger', new TranslatableMessage('emailPartiallySent', ['%count%' => $exception->delivered, '%total%' => \count($recipients), '%error%' => $exception->getMessage()]));

                    return $this->redirect($backUrl, Response::HTTP_SEE_OTHER);
                }

                if ($exception->isAuthenticationFailure()) {
                    // Ask for the password (again).
                    $this->credentials->clear();
                    $form = $this->createComposeForm($data, $choices, true);
                    $this->addFlash('danger', new TranslatableMessage('emailAuthenticationFailed'));
                } else {
                    $this->addFlash('danger', new TranslatableMessage('emailNotSent', ['%error%' => $exception->getMessage()]));
                }

                return $this->renderForm($form, $user, $skipped, ['backUrl' => $backUrl, ...$context], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($password === null) {
                // The password has been accepted by the mail server, so it doesn't have to be entered again.
                $this->credentials->store($user, (string) $data->password);
            }

            $this->addFlash('success', new TranslatableMessage('emailSent', ['%count%' => $count]));

            return $this->redirect($backUrl, Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm($form, $user, $skipped, ['backUrl' => $backUrl, ...$context], $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    /**
     * Creates the form.
     *
     * @param ComposeData $data The form data.
     * @param array<string, string> $choices The recipients the user may write to (email address => form value).
     * @param bool $askPassword Whether to ask for the user's password.
     *
     * @return FormInterface<ComposeData> The form.
     */
    private function createComposeForm(ComposeData $data, array $choices, bool $askPassword): FormInterface
    {
        return $this->createForm(ComposeType::class, $data, [
            'recipients' => $choices,
            'ask_password' => $askPassword
        ]);
    }

    /**
     * Returns the values marked in the table of the domain or list page.
     *
     * @param Request $request The current request.
     *
     * @return list<string> The selected values.
     */
    private function getSelection(Request $request): array
    {
        $values = [...$request->query->all('selection'), ...$request->request->all('selection')];

        return array_values(array_filter($values, \is_string(...)));
    }

    /**
     * Renders the form page.
     *
     * @param FormInterface<ComposeData> $form The form.
     * @param Account $user The current user (the sender).
     * @param list<string> $skipped The selected addresses that cannot receive emails.
     * @param array<string, mixed> $context Additional template variables (domain, list and backUrl).
     * @param int $status The HTTP status code.
     */
    private function renderForm(FormInterface $form, Account $user, array $skipped, array $context, int $status): Response
    {
        return $this->render('pages/manage/compose.html.twig', [
            'form' => $form,
            'sender' => $user,
            'skipped' => $skipped,
            ...$context
        ], new Response(status: $status));
    }

    /**
     * @param AccountRepository $accountRepository The repository for accounts.
     * @param SubmissionCredentials $credentials The store for the user's mail password.
     * @param BulkMailer $mailer The service for sending the email.
     * @param RecipientPolicy $recipientPolicy The rules for which accounts can receive emails.
     */
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly SubmissionCredentials $credentials,
        private readonly BulkMailer $mailer,
        private readonly RecipientPolicy $recipientPolicy
    ) {
    }
}
