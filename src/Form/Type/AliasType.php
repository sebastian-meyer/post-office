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

use App\Form\Model\AccountData;
use App\Form\Model\AliasData;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form for creating or editing an alias.
 */
final class AliasType extends AbstractAccountType
{
    /**
     * Adds the alias fields.
     *
     * @param FormBuilderInterface $builder The form builder.
     * @phpstan-param FormBuilderInterface<AccountData|null> $builder
     * @psalm-param FormBuilderInterface<AccountData> $builder
     * @param array<string, mixed> $options The form options.
     *
     * @see AbstractAccountType::buildForm()
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('destination', EmailType::class, [
            'label' => 'destination'
        ]);
    }

    /**
     * Sets the data class.
     *
     * @param OptionsResolver $resolver The options resolver.
     *
     * @see AbstractAccountType::configureOptions()
     */
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('data_class', AliasData::class);
    }
}
