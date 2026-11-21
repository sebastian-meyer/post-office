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

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form data for writing an email to accounts of a domain or members of a mailing list.
 */
final class ComposeData
{
    /**
     * The selected recipients (the values of the form's recipient choices).
     *
     * @var list<string>
     */
    #[Assert\Count(min: 1, minMessage: 'noRecipients')]
    public array $recipients = [];

    /**
     * The subject.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $subject = null;

    /**
     * The plain text body.
     */
    #[Assert\NotBlank]
    public ?string $body = null;

    /**
     * The user's password, if it is not kept in the session (see App\Security\SubmissionCredentials).
     */
    public ?string $password = null;
}
