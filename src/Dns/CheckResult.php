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

namespace App\Dns;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * The result of a DNS check: its findings, the records found and a suggested record.
 */
final class CheckResult
{
    /**
     * The overall status (the most severe status of the findings).
     */
    public private(set) CheckStatus $status = CheckStatus::Ok;

    /**
     * The findings, each with its status.
     *
     * @var list<array{status: CheckStatus, message: TranslatableMessage}>
     */
    public private(set) array $findings = [];

    /**
     * The relevant records found.
     *
     * @var list<string>
     */
    public private(set) array $records = [];

    /**
     * A record to add or change, if any.
     *
     * @var ?array{name: string, type: string, value: string}
     */
    public private(set) ?array $suggestion = null;

    /**
     * Adds a finding.
     *
     * @param CheckStatus $status The status of the finding.
     * @param string $message The translation key (in the "messages" domain, prefixed with "dns.").
     * @param array<string, string|int> $parameters The translation parameters.
     */
    public function add(CheckStatus $status, string $message, array $parameters = []): self
    {
        $this->findings[] = ['status' => $status, 'message' => new TranslatableMessage('dns.' . $message, $parameters)];
        $this->status = $this->status->worst($status);

        return $this;
    }

    /**
     * Adds records found.
     *
     * @param list<string> $records The records.
     */
    public function addRecords(array $records): self
    {
        $this->records = [...$this->records, ...$records];

        return $this;
    }

    /**
     * Suggests a record to add or change (the first suggestion is kept).
     *
     * @param string $name The record's name.
     * @param string $type The record's type.
     * @param string $value The record's value.
     */
    public function suggest(string $name, string $type, string $value): self
    {
        $this->suggestion ??= ['name' => $name, 'type' => $type, 'value' => $value];

        return $this;
    }

    /**
     * @param string $name The check's name (translation key, prefixed with "dns.").
     */
    public function __construct(
        public readonly string $name
    ) {
    }
}
