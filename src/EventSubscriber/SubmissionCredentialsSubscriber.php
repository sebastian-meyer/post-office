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

namespace App\EventSubscriber;

use App\Entity\Account;
use App\Security\SubmissionCredentials;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use WeakMap;

/**
 * Keeps the password of a successful login for sending emails with the user's credentials (see SubmissionCredentials).
 *
 * The password is erased from the passport once it has been checked, so it is picked up just before that and only
 * stored in the session after the login succeeded.
 */
final class SubmissionCredentialsSubscriber implements EventSubscriberInterface
{
    /**
     * The presented passwords of the passports being checked.
     *
     * @var WeakMap<Passport, string>
     */
    private WeakMap $passwords;

    /**
     * Register subscribed events.
     *
     * @see EventSubscriberInterface::getSubscribedEvents()
     */
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            // Before CheckCredentialsListener (priority 0) verifies and erases the password.
            CheckPassportEvent::class => ['capturePassword', 10],
            LoginSuccessEvent::class => 'storePassword'
        ];
    }

    /**
     * Picks up the presented password before it is checked.
     *
     * @param CheckPassportEvent $event The event.
     */
    public function capturePassword(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        $badge = $passport->getBadge(PasswordCredentials::class);

        if ($badge instanceof PasswordCredentials && !$badge->isResolved()) {
            $this->passwords[$passport] = $badge->getPassword();
        }
    }

    /**
     * Stores the password in the session after a successful login.
     *
     * @param LoginSuccessEvent $event The event.
     */
    public function storePassword(LoginSuccessEvent $event): void
    {
        $passport = $event->getPassport();
        $password = $this->passwords[$passport] ?? null;
        unset($this->passwords[$passport]);

        $user = $event->getUser();

        if ($password !== null && $user instanceof Account) {
            $this->credentials->store($user, $password);
        }
    }

    /**
     * @param SubmissionCredentials $credentials The store for the user's mail password.
     */
    public function __construct(
        private readonly SubmissionCredentials $credentials
    ) {
        $this->passwords = new WeakMap();
    }
}
