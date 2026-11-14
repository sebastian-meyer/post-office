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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Controller for authentication and password management.
 */
#[Template('pages/auth.html.twig')]
final class AuthController extends AbstractController
{
    /**
     * Shows and handles the form for changing the user's password.
     *
     * @return array<string, mixed>|Response The template variables or a redirect after success.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/change-password', name: 'change_password')]
    public function changePassword(
        Request $request,
        #[CurrentUser] Account $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $hasher,
        #[MapQueryParameter('success')] ?bool $success = false
    ): array|Response {
        $form = $this->createFormBuilder()
            ->add(
                'currentPassword',
                PasswordType::class,
                [
                    'constraints' => [
                        new Assert\NotBlank(),
                        new UserPassword()
                    ]
                ]
            )
            ->add(
                'newPassword',
                PasswordType::class,
                [
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Expression(
                            expression: 'this.getParent().get("currentPassword").getData() !== this.getData()',
                            message: 'newPasswordSameAsCurrent'
                        ),
                        new Assert\NotCompromisedPassword(),
                        new Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_STRONG)
                    ]
                ]
            )
            ->add(
                'newPasswordRepeat',
                PasswordType::class,
                [
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Expression(
                            expression: 'this.getParent().get("newPassword").getData() === this.getData()',
                            message: 'passwordsNotMatching'
                        )
                    ]
                ]
            )
            ->getForm();

        $form->handleRequest($request);

        $newPassword = $form->get('newPassword')->getData();

        if ($form->isSubmitted() && $form->isValid() && \is_string($newPassword)) {
            $hashedPassword = $hasher->hashPassword($user, $newPassword);
            $entityManager->getRepository(Account::class)->upgradePassword($user, $hashedPassword);

            return $this->redirectToRoute('change_password', ['success' => true]);
        }

        return [
            'form' => $form,
            'success' => $success
        ];
    }

    /**
     * Shows the login form (the login itself is handled by the security firewall).
     *
     * @return array<string, mixed> The template variables.
     */
    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): array
    {
        return [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'lastUsername' => $authenticationUtils->getLastUsername()
        ];
    }
}
