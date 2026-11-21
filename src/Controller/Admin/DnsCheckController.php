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

namespace App\Controller\Admin;

use App\Dns\DnsHealthCheck;
use App\Entity\Domain;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for the DNS check of a domain (administrators only, since only they can change the mail server setup).
 */
#[IsGranted('ROLE_ADMIN')]
final class DnsCheckController extends AbstractController
{
    /**
     * Checks the DNS records of the domain.
     *
     * @param Domain $domain The domain.
     * @param DnsHealthCheck $healthCheck The DNS check.
     * @param string $mailHostname The host name of this mail server (MAIL_HOSTNAME), or empty.
     *
     * @return array<string, mixed> The template variables.
     */
    #[Route('/admin/domains/{domain}/dns', name: 'admin_domain_dns', requirements: ['domain' => '[^/]+'], methods: ['GET'])]
    #[Template('pages/admin/dns.html.twig')]
    public function check(
        #[MapEntity(id: 'domain')] Domain $domain,
        DnsHealthCheck $healthCheck,
        #[Autowire(param: 'app.mail_hostname')] string $mailHostname
    ): array {
        return [
            'domain' => $domain,
            'results' => $healthCheck->check($domain),
            'mailHostname' => trim($mailHostname)
        ];
    }
}
