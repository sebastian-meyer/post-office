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

namespace App\Dns\Check;

use App\Dns\CheckResult;
use App\Dns\CheckStatus;
use App\Dns\DnsLookup;
use App\Dns\DomainCheck;
use App\Entity\Domain;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Checks that the IP addresses of this mail server (MAIL_HOSTNAME) point back to its host name (PTR records).
 *
 * Many mail servers reject emails from servers without matching reverse DNS. This doesn't depend on the domain, but
 * is shown with every domain's check, since it affects all of them. It is skipped if MAIL_HOSTNAME is not set.
 */
#[AsTaggedItem(priority: 90)]
final class ReverseDnsCheck implements DomainCheck
{
    /**
     * Checks the PTR records.
     *
     * @see DomainCheck::check()
     */
    #[\Override]
    public function check(Domain $domain): ?CheckResult
    {
        $hostname = strtolower(rtrim(trim($this->mailHostname), '.'));

        if ($hostname === '') {
            return null;
        }

        $result = new CheckResult('ptr');
        $addresses = $this->lookup->addresses($hostname);

        if ($addresses === []) {
            return $result->add(CheckStatus::Error, 'ptr.noAddress', ['%hostname%' => $hostname]);
        }

        foreach ($addresses as $address) {
            $names = array_map(
                static fn (string $name): string => strtolower($name),
                $this->lookup->resolver->query($this->reverseName($address), 'PTR')->records
            );
            $result->addRecords(array_map(static fn (string $name): string => "{$address} → {$name}", $names));

            if (!\in_array($hostname, $names, true)) {
                $result->add(CheckStatus::Error, 'ptr.mismatch', ['%address%' => $address, '%hostname%' => $hostname]);
            }
        }

        return $result->status === CheckStatus::Ok ? $result->add(CheckStatus::Ok, 'ptr.ok', ['%hostname%' => $hostname]) : $result;
    }

    /**
     * Returns the name for the reverse lookup of an IP address (in-addr.arpa or ip6.arpa).
     *
     * @param string $address The IP address.
     */
    private function reverseName(string $address): string
    {
        $bytes = filter_var($address, FILTER_VALIDATE_IP) !== false ? inet_pton($address) : false;

        if ($bytes === false) {
            return $address;
        }

        if (\strlen($bytes) === 4) {
            return implode('.', array_reverse(explode('.', $address))) . '.in-addr.arpa';
        }

        return implode('.', array_reverse(str_split(bin2hex($bytes)))) . '.ip6.arpa';
    }

    /**
     * @param DnsLookup $lookup The DNS lookups.
     * @param string $mailHostname The host name of this mail server (MAIL_HOSTNAME), or empty.
     */
    public function __construct(
        private readonly DnsLookup $lookup,
        #[Autowire(param: 'app.mail_hostname')]
        private readonly string $mailHostname = ''
    ) {
    }
}
