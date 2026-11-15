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

use App\Entity\Blacklist;
use App\Form\Model\BlacklistData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form for creating or editing a blacklist entry.
 *
 * Options:
 * - is_new: Whether a new entry is created. Only then the address can be set.
 *
 * @extends AbstractType<BlacklistData>
 */
final class BlacklistType extends AbstractType
{
    /**
     * Adds the fields.
     *
     * @param FormBuilderInterface $builder The form builder.
     * @phpstan-param FormBuilderInterface<BlacklistData|null> $builder
     * @psalm-param FormBuilderInterface<BlacklistData> $builder
     * @param array<string, mixed> $options The form options.
     *
     * @see AbstractType::buildForm()
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['is_new'] === true) {
            $builder->add('address', TextType::class, [
                'label' => 'blacklistAddress',
                'help' => 'blacklistAddressHelp'
            ]);
        }

        $builder
            ->add('action', ChoiceType::class, [
                'label' => 'blacklistAction',
                'choices' => array_combine(
                    array_map(static fn (string $action): string => 'blacklistAction.' . $action, Blacklist::ACTIONS),
                    Blacklist::ACTIONS
                )
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'enabled',
                'required' => false
            ]);
    }

    /**
     * Configures the options.
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
                'data_class' => BlacklistData::class,
                'is_new' => false,
                'validation_groups' => static fn (Options $options): array => $options['is_new'] === true ? ['Default', 'create'] : ['Default']
            ])
            ->setAllowedTypes('is_new', 'bool');
    }
}
