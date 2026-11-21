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
use App\Dns\SpfEvaluator;
use App\Entity\Domain;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Checks the SPF record: it must exist only once, end with a strict "all", and allow this mail server to send.
 */
#[AsTaggedItem(priority: 60)]
final class SpfCheck implements DomainCheck
{
    /**
     * Checks the SPF record.
     *
     * @see DomainCheck::check()
     */
    #[\Override]
    public function check(Domain $domain): CheckResult
    {
        $result = new CheckResult('spf');
        $records = $this->lookup->txt($domain->domain, 'v=spf1');

        if ($records === null) {
            return $result->add(CheckStatus::Error, 'lookupFailed');
        }

        $result->addRecords($records);

        if (\count($records) !== 1) {
            $result->add(CheckStatus::Error, $records === [] ? 'spf.missing' : 'spf.multiple');

            return $result->suggest($domain->domain, 'TXT', 'v=spf1 mx -all');
        }

        $this->checkAll($result, $records[0]);
        $this->checkServer($result, $domain);

        return $result->status === CheckStatus::Ok ? $result->add(CheckStatus::Ok, 'spf.ok') : $result;
    }

    /**
     * Checks the "all" mechanism at the end of the record.
     *
     * @param CheckResult $result The result.
     * @param string $record The SPF record.
     */
    private function checkAll(CheckResult $result, string $record): void
    {
        if (preg_match('/\sredirect=/i', $record) === 1) {
            return;
        }

        preg_match('/\s([+?~-]?)all$/i', trim($record), $matches);

        match ($matches[1] ?? null) {
            '', '+' => $result->add(CheckStatus::Error, 'spf.passAll'),
            '?', null => $result->add(CheckStatus::Warning, 'spf.weak'),
            default => null
        };
    }

    /**
     * Checks that the record allows this mail server (MAIL_HOSTNAME) to send emails.
     *
     * @param CheckResult $result The result.
     * @param Domain $domain The domain.
     */
    private function checkServer(CheckResult $result, Domain $domain): void
    {
        $hostname = strtolower(rtrim(trim($this->mailHostname), '.'));

        if ($hostname === '') {
            return;
        }

        $addresses = $this->lookup->addresses($hostname);

        if ($addresses === null) {
            $result->add(CheckStatus::Error, 'lookupFailed');

            return;
        }

        foreach ($addresses as $address) {
            $authorized = $this->evaluator->authorizes($domain->domain, $address);

            if ($authorized === false) {
                $result->add(CheckStatus::Error, 'spf.serverNotAllowed', ['%hostname%' => $hostname, '%address%' => $address]);
            } elseif ($authorized === null) {
                $result->add(CheckStatus::Warning, 'spf.unverifiable', ['%address%' => $address]);
            }
        }
    }

    /**
     * @param DnsLookup $lookup The DNS lookups.
     * @param SpfEvaluator $evaluator The SPF evaluator.
     * @param string $mailHostname The host name of this mail server (MAIL_HOSTNAME), or empty.
     */
    public function __construct(
        private readonly DnsLookup $lookup,
        private readonly SpfEvaluator $evaluator,
        #[Autowire(param: 'app.mail_hostname')]
        private readonly string $mailHostname = ''
    ) {
    }
}
