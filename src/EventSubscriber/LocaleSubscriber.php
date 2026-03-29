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

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Determine and set user's locale.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(private ContainerBagInterface $params)
    {
    }

    /**
     * Register subscribed events.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => ['setLocale', 120]
        ];
    }

    /**
     * Set locale to user's preferred language.
     */
    public function setLocale(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $locale = $request->getPreferredLanguage($this->params->get('kernel.enabled_locales'));
        $request->setLocale($locale);
    }
}
