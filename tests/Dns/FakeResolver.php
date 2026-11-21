<?php

declare(strict_types=1);

namespace App\Tests\Dns;

use App\Dns\DnsAnswer;
use App\Dns\DnsResolver;

/**
 * Answers DNS queries from a fixed set of records (used instead of real DNS in tests, see config/services.yaml).
 */
final class FakeResolver implements DnsResolver
{
    /**
     * @var array<string, DnsAnswer>
     */
    private array $answers = [];

    /**
     * Sets the answer for a query.
     *
     * @param list<string> $records The records.
     */
    public function set(string $name, string $type, array $records, bool $signed = false, bool $authenticated = false): self
    {
        $this->answers[strtoupper($type) . ' ' . $name] = new DnsAnswer($records, $signed, $authenticated);

        return $this;
    }

    /**
     * Lets a query fail.
     */
    public function fail(string $name, string $type): self
    {
        $this->answers[strtoupper($type) . ' ' . $name] = new DnsAnswer(error: 'SERVFAIL');

        return $this;
    }

    public function query(string $name, string $type): DnsAnswer
    {
        return $this->answers[strtoupper($type) . ' ' . $name] ?? new DnsAnswer();
    }
}
