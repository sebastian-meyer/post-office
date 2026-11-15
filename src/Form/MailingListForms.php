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

namespace App\Form;

use App\Entity\Account;
use App\Form\Model\AccountData;
use App\Form\Model\MailingListData;
use App\Form\Model\ManagerData;
use App\Form\Model\MemberData;
use App\Form\Type\MailingListType;
use App\Form\Type\ManagerType;
use App\Form\Type\MemberType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Creates the forms of a mailing list's page.
 *
 * The page contains several forms, each posting to its own route. They have distinct names, so only the submitted
 * one handles a request.
 */
final class MailingListForms
{
    /**
     * Creates the form for the list's settings.
     *
     * @param Account $list The mailing list.
     * @param ?MailingListData $data The form data, or NULL to use the list's current settings.
     *
     * @return FormInterface<AccountData> The form.
     */
    public function settings(Account $list, ?MailingListData $data = null): FormInterface
    {
        return $this->formFactory->createNamed('settings', MailingListType::class, $data ?? MailingListData::fromAccount($list), [
            'action' => $this->urlGenerator->generate('manage_list', ['id' => $list->id])
        ]);
    }

    /**
     * Creates the form for adding a member.
     *
     * @param Account $list The mailing list.
     *
     * @return FormInterface<MemberData> The form.
     */
    public function member(Account $list): FormInterface
    {
        return $this->formFactory->createNamed('member', MemberType::class, new MemberData(), [
            'action' => $this->urlGenerator->generate('manage_list_member_add', ['id' => $list->id])
        ]);
    }

    /**
     * Creates the form for appointing a list manager.
     *
     * @param Account $list The mailing list.
     *
     * @return FormInterface<ManagerData> The form.
     */
    public function manager(Account $list): FormInterface
    {
        return $this->formFactory->createNamed('manager', ManagerType::class, new ManagerData($list->domain), [
            'action' => $this->urlGenerator->generate('manage_list_manager_add', ['id' => $list->id])
        ]);
    }

    /**
     * @param FormFactoryInterface $formFactory The form factory.
     * @param UrlGeneratorInterface $urlGenerator The URL generator (for the forms' actions).
     */
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }
}
