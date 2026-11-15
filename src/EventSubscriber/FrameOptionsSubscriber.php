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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Forbids embedding the application's pages in frames on other sites (protection against clickjacking).
 *
 * Many actions are a single click on a button (e.g. enabling or disabling an account), which a malicious site could
 * otherwise trick a logged-in user into by overlaying an invisible frame.
 *
 * Headers which are already set (e.g. by a controller) are left unchanged.
 */
final class FrameOptionsSubscriber implements EventSubscriberInterface
{
    /**
     * Register subscribed events.
     *
     * @return array<string, string>
     *
     * @see EventSubscriberInterface::getSubscribedEvents()
     */
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ResponseEvent::class => 'addHeaders'
        ];
    }

    /**
     * Adds the headers forbidding frames to the response.
     *
     * @param ResponseEvent $event The response event.
     */
    public function addHeaders(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;

        // The modern standard, which takes precedence in all current browsers.
        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        }

        // For older browsers.
        if (!$headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'DENY');
        }
    }
}
