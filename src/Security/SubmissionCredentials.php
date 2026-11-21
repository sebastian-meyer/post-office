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

namespace App\Security;

use App\Entity\Account;
use SodiumException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Keeps the user's mail password in the session, so emails can be sent with the user's own credentials.
 *
 * The password hashes in the database cannot be used to log in to the mail server, so the plain password is kept
 * from the login (see App\EventSubscriber\SubmissionCredentialsSubscriber). It is encrypted with a key derived from
 * the application secret and bound to the user's email address, so the session storage alone does not reveal it.
 * The session (and with it the password) is discarded when the user logs out.
 */
final class SubmissionCredentials
{
    /**
     * The session attribute holding the encrypted password.
     */
    private const string SESSION_KEY = '_submission_credentials';

    /**
     * The context for deriving the encryption key from the application secret.
     */
    private const string KEY_CONTEXT = 'post-office/submission-credentials';

    /**
     * Removes the password from the session (e.g. after the mail server rejected it).
     */
    public function clear(): void
    {
        $this->getSession()?->remove(self::SESSION_KEY);
    }

    /**
     * Returns the user's password, or NULL if none is kept for the user.
     *
     * @param Account $user The current user.
     */
    public function get(Account $user): ?string
    {
        $encrypted = $this->getSession()?->get(self::SESSION_KEY);

        if (!\is_string($encrypted)) {
            return null;
        }

        $decoded = base64_decode($encrypted, true);
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

        if ($decoded === false || \strlen($decoded) <= $nonceLength) {
            return null;
        }

        try {
            $password = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                substr($decoded, $nonceLength),
                $user->getUserIdentifier(),
                substr($decoded, 0, $nonceLength),
                $this->getKey()
            );
        } catch (SodiumException) {
            return null;
        }

        return \is_string($password) ? $password : null;
    }

    /**
     * Keeps the user's password in the session.
     *
     * @param Account $user The current user.
     * @param string $password The user's plain password.
     */
    public function store(Account $user, #[\SensitiveParameter] string $password): void
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $encrypted = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($password, $user->getUserIdentifier(), $nonce, $this->getKey());

        $this->getSession()?->set(self::SESSION_KEY, base64_encode($nonce . $encrypted));
    }

    /**
     * Derives the encryption key from the application secret.
     */
    private function getKey(): string
    {
        // The application secret can have any length, while BLAKE2b keys must have 16 to 64 bytes.
        return sodium_crypto_generichash($this->secret, self::KEY_CONTEXT, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    }

    /**
     * Returns the current session, if there is one.
     */
    private function getSession(): ?SessionInterface
    {
        try {
            return $this->requestStack->getSession();
        } catch (SessionNotFoundException) {
            return null;
        }
    }

    /**
     * @param RequestStack $requestStack The request stack (to get the session).
     * @param string $secret The application secret.
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        #[Autowire(param: 'kernel.secret')]
        #[\SensitiveParameter]
        private readonly string $secret
    ) {
    }
}
