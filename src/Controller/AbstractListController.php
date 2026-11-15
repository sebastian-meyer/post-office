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
use App\Form\MailingListForms;
use App\Form\Model\AccountData;
use App\Form\Model\ManagerData;
use App\Form\Model\MemberData;
use App\Repository\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for controllers of the mailing list page.
 */
abstract class AbstractListController extends AbstractController
{
    /**
     * Renders the mailing list page with all its forms.
     *
     * Forms that are not given are created new. A submitted form is shown with its errors (status 422).
     *
     * @param Account $list The mailing list.
     * @param ?FormInterface<AccountData> $settings The settings form.
     * @param ?FormInterface<MemberData> $member The form for adding a member.
     * @param ?FormInterface<ManagerData> $manager The form for appointing a list manager.
     */
    protected function renderListPage(
        Account $list,
        ?FormInterface $settings = null,
        ?FormInterface $member = null,
        ?FormInterface $manager = null
    ): Response {
        $submitted = ($settings?->isSubmitted() ?? false) || ($member?->isSubmitted() ?? false) || ($manager?->isSubmitted() ?? false);

        return $this->render('pages/manage/list.html.twig', [
            'list' => $list,
            'settingsForm' => $settings ?? $this->listForms->settings($list),
            'memberForm' => $member ?? $this->listForms->member($list),
            'managerForm' => $manager ?? $this->listForms->manager($list),
            // Suggestions for the manager form (eligible accounts which are not yet managers).
            'managerCandidates' => array_values(array_filter(
                $this->accountRepository->findEligibleManagers($list->domain),
                static fn (Account $account): bool => !$list->managers->contains($account)
            ))
        ], new Response(status: $submitted ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @param MailingListForms $listForms The factory for the forms of the mailing list page.
     * @param AccountRepository $accountRepository The repository for accounts.
     */
    public function __construct(
        protected readonly MailingListForms $listForms,
        private readonly AccountRepository $accountRepository
    ) {
    }
}
