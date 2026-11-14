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

use App\Entity\Domain;
use App\Form\Model\AccountData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Base form type for all account types.
 *
 * Options:
 * - is_new: Whether a new account is created. Only then username and domain can be set.
 * - domains: The domains to choose from when creating a new account (i.e. those the user can manage).
 *
 * @extends AbstractType<AccountData>
 */
abstract class AbstractAccountType extends AbstractType
{
    /**
     * Adds the common fields.
     *
     * @param FormBuilderInterface $builder The form builder.
     * @phpstan-param FormBuilderInterface<AccountData|null> $builder
     * @psalm-param FormBuilderInterface<AccountData> $builder
     * @param array<string, mixed> $options The form options.
     *
     * @see AbstractType::buildForm()
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['is_new'] === true) {
            $builder
                ->add('username', TextType::class, [
                    'label' => 'username',
                    'required' => $this->isUsernameRequired(),
                    'help' => $this->isUsernameRequired() ? null : 'catchAllHelp'
                ])
                ->add('domain', EntityType::class, [
                    'label' => 'domain',
                    'class' => Domain::class,
                    'choices' => $options['domains'],
                    'choice_label' => static fn (Domain $domain): string => '@' . $domain->domain
                ]);
        }

        $builder
            ->add('label', TextType::class, [
                'label' => 'label',
                'required' => false
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'enabled',
                'required' => false
            ]);
    }

    /**
     * Configures the common options.
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
                'is_new' => false,
                'domains' => [],
                'validation_groups' => static fn (Options $options): array => $options['is_new'] === true ? ['Default', 'create'] : ['Default']
            ])
            ->setAllowedTypes('is_new', 'bool')
            ->setAllowedTypes('domains', Domain::class . '[]');
    }

    /**
     * Whether the username is required, i.e. catch-all addresses are not allowed.
     */
    protected function isUsernameRequired(): bool
    {
        return false;
    }
}
