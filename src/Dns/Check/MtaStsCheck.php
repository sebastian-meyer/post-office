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
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Checks the optional MTA-STS setup (RFC 8461): the TXT record and the policy file, whose MX patterns must cover all
 * mail exchangers of the domain.
 */
#[AsTaggedItem(priority: 30)]
final class MtaStsCheck implements DomainCheck
{
    /**
     * Checks the MTA-STS record and policy.
     *
     * @see DomainCheck::check()
     */
    #[\Override]
    public function check(Domain $domain): CheckResult
    {
        $result = new CheckResult('mtasts');
        $records = $this->lookup->txt("_mta-sts.{$domain->domain}", 'v=STSv1');

        if ($records === null) {
            return $result->add(CheckStatus::Error, 'lookupFailed');
        }

        $result->addRecords($records);

        if ($records === []) {
            return $result->add(CheckStatus::Info, 'mtasts.missing');
        }

        if (\count($records) > 1) {
            return $result->add(CheckStatus::Error, 'mtasts.multiple');
        }

        $url = "https://mta-sts.{$domain->domain}/.well-known/mta-sts.txt";

        try {
            // Redirects are not allowed (RFC 8461, section 3.3).
            $policy = $this->parsePolicy($this->httpClient->request('GET', $url, ['max_redirects' => 0, 'timeout' => 5])->getContent());
        } catch (ExceptionInterface $exception) {
            return $result->add(CheckStatus::Error, 'mtasts.policyUnavailable', ['%url%' => $url, '%error%' => $exception->getMessage()]);
        }

        $result->addRecords(array_map(static fn (string $pattern): string => "{$url}: mx: {$pattern}", $policy['mx']));

        return $this->checkPolicy($result, $policy, $this->lookup->mxHosts($domain->domain) ?? []);
    }

    /**
     * Checks the mode and MX patterns of the policy.
     *
     * @param CheckResult $result The result.
     * @param array{version: string, mode: string, mx: list<string>} $policy The policy.
     * @param list<string> $hosts The mail exchangers of the domain.
     */
    private function checkPolicy(CheckResult $result, array $policy, array $hosts): CheckResult
    {
        if ($policy['version'] !== 'STSv1' || !\in_array($policy['mode'], ['enforce', 'testing', 'none'], true)) {
            return $result->add(CheckStatus::Error, 'mtasts.invalidPolicy');
        }

        foreach ($hosts as $host) {
            if (!$this->matchesAny($host, $policy['mx'])) {
                $result->add(CheckStatus::Error, 'mtasts.mxMismatch', ['%host%' => $host]);
            }
        }

        return match ($policy['mode']) {
            'enforce' => $result->status === CheckStatus::Ok ? $result->add(CheckStatus::Ok, 'mtasts.ok') : $result,
            'testing' => $result->add(CheckStatus::Info, 'mtasts.testing'),
            default => $result->add(CheckStatus::Warning, 'mtasts.none')
        };
    }

    /**
     * Whether the host matches one of the MX patterns (a leading "*." matches exactly one label).
     *
     * @param string $host The host name.
     * @param list<string> $patterns The MX patterns of the policy.
     */
    private function matchesAny(string $host, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = strtolower(rtrim($pattern, '.'));

            if ($pattern === $host || (str_starts_with($pattern, '*.') && preg_match('/^[^.]+' . preg_quote(substr($pattern, 1), '/') . '$/', $host) === 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parses the policy file ("key: value" lines).
     *
     * @param string $content The content of the policy file.
     *
     * @return array{version: string, mode: string, mx: list<string>} The policy.
     */
    private function parsePolicy(string $content): array
    {
        $policy = ['version' => '', 'mode' => '', 'mx' => []];

        foreach (explode("\n", str_replace("\r", '', $content)) as $line) {
            [$key, $value] = array_map(trim(...), explode(':', $line, 2) + [1 => '']);

            match (strtolower($key)) {
                'version' => $policy['version'] = $value,
                'mode' => $policy['mode'] = strtolower($value),
                'mx' => $policy['mx'][] = $value,
                default => null
            };
        }

        return $policy;
    }

    /**
     * @param DnsLookup $lookup The DNS lookups.
     * @param HttpClientInterface $httpClient The HTTP client (to fetch the policy file).
     */
    public function __construct(
        private readonly DnsLookup $lookup,
        private readonly HttpClientInterface $httpClient
    ) {
    }
}
