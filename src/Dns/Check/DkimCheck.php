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
 * Checks the DKIM public keys for the selectors the mail server signs with (DKIM_SELECTORS).
 *
 * At least one of the selectors must have a valid key. The selectors can't be found out from DNS, so the check only
 * gives a hint if DKIM_SELECTORS is not set.
 */
#[AsTaggedItem(priority: 50)]
final class DkimCheck implements DomainCheck
{
    /**
     * Checks the DKIM records.
     *
     * @see DomainCheck::check()
     */
    #[\Override]
    public function check(Domain $domain): CheckResult
    {
        $result = new CheckResult('dkim');
        $selectors = array_values(array_filter(array_map(trim(...), $this->selectors), static fn (string $selector): bool => $selector !== ''));

        if ($selectors === []) {
            return $result->add(CheckStatus::Info, 'dkim.notConfigured');
        }

        $findings = [];

        foreach ($selectors as $selector) {
            $name = "{$selector}._domainkey.{$domain->domain}";
            $answer = $this->lookup->resolver->query($name, 'TXT');
            $keys = array_values(array_filter($answer->records, static fn (string $record): bool => preg_match('/(^|;)\s*p=/i', $record) === 1));
            $result->addRecords(array_map(static fn (string $record): string => "{$name} {$record}", $keys));

            [$status, $message] = match (true) {
                $answer->failed() => [CheckStatus::Error, 'lookupFailed'],
                $keys === [] => [CheckStatus::Error, 'dkim.missing'],
                // Verifiers may pick any of them, so the result would depend on chance.
                \count($keys) > 1 => [CheckStatus::Error, 'dkim.multiple'],
                preg_match('/(^|;)\s*p=\s*(;|$)/i', $keys[0] ?? '') === 1 => [CheckStatus::Error, 'dkim.revoked'],
                default => [CheckStatus::Ok, 'dkim.ok']
            };
            $findings[] = ['status' => $status, 'message' => $message, 'name' => $name];
        }

        // One valid key is enough (e.g. while keys are rotated), so other missing keys are only reported.
        $valid = \in_array(CheckStatus::Ok, array_column($findings, 'status'), true);

        foreach ($findings as $finding) {
            $status = $valid && $finding['status'] === CheckStatus::Error ? CheckStatus::Info : $finding['status'];
            $result->add($status, $finding['message'], ['%name%' => $finding['name']]);
        }

        return $result;
    }

    /**
     * @param DnsLookup $lookup The DNS lookups.
     * @param list<string> $selectors The DKIM selectors (DKIM_SELECTORS).
     */
    public function __construct(
        private readonly DnsLookup $lookup,
        #[Autowire(param: 'app.dkim_selectors')]
        private readonly array $selectors = []
    ) {
    }
}
