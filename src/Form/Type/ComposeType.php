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

namespace App\Form\Type;

use App\Form\Model\ComposeData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form for writing an email to accounts of a domain or members of a mailing list.
 *
 * The recipients are chosen from the given choices (email address => value), so only addresses the user may write to
 * can be submitted.
 *
 * @extends AbstractType<ComposeData>
 */
final class ComposeType extends AbstractType
{
    /**
     * Adds the recipients, subject and body fields, and the copy and password fields if requested.
     *
     * @param FormBuilderInterface $builder The form builder.
     * @phpstan-param FormBuilderInterface<ComposeData|null> $builder
     * @psalm-param FormBuilderInterface<ComposeData> $builder
     * @param array<string, mixed> $options The form options.
     *
     * @see AbstractType::buildForm()
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipients', ChoiceType::class, [
                'label' => 'recipients',
                'choices' => $options['recipients'],
                'choice_translation_domain' => false,
                'multiple' => true,
                'expanded' => true
            ])
            ->add('subject', TextType::class, [
                'label' => 'subject'
            ])
            ->add('body', TextareaType::class, [
                'label' => 'message',
                'attr' => ['rows' => 12]
            ]);

        if ($options['allow_copy'] === true) {
            $builder->add('copy', CheckboxType::class, [
                'label' => 'sendCopy',
                'help' => 'sendCopyHelp',
                'required' => false
            ]);
        }

        if ($options['ask_password'] === true) {
            $builder->add('password', PasswordType::class, [
                'label' => 'yourPassword',
                'help' => 'composePasswordHelp',
                'constraints' => [new Assert\NotBlank()]
            ]);
        }
    }

    /**
     * Sets the data class and the options for the recipient choices and the copy and password fields.
     *
     * @param OptionsResolver $resolver The options resolver.
     *
     * @see AbstractType::configureOptions()
     */
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => ComposeData::class,
                'allow_copy' => false,
                'ask_password' => false
            ])
            ->setRequired('recipients')
            ->setAllowedTypes('recipients', 'array')
            ->setAllowedTypes('allow_copy', 'bool')
            ->setAllowedTypes('ask_password', 'bool');
    }
}
