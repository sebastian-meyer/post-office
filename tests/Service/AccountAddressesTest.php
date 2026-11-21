<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Destination;
use App\Entity\Domain;
use App\Service\AccountAddresses;
use PHPUnit\Framework\TestCase;

final class AccountAddressesTest extends TestCase
{
    public function testBelongsTo(): void
    {
        $bob = new Account('bob', new Domain('example.com'));

        self::assertTrue(AccountAddresses::belongsTo('bob@example.com', $bob));
        self::assertTrue(AccountAddresses::belongsTo(' BOB@Example.com ', $bob));
        self::assertTrue(AccountAddresses::belongsTo('bob+news@example.com', $bob));
        self::assertTrue(AccountAddresses::belongsTo('bob+news+more@example.com', $bob));
        self::assertFalse(AccountAddresses::belongsTo('bobby@example.com', $bob));
        self::assertFalse(AccountAddresses::belongsTo('bob@example.org', $bob));
        self::assertFalse(AccountAddresses::belongsTo('bob@sub.example.com', $bob));
        self::assertFalse(AccountAddresses::belongsTo('alice+bob@example.com', $bob));
    }

    public function testCatchAllHasNoAddresses(): void
    {
        $catchAll = new Account(null, new Domain('example.com'), AccountType::Alias, null, new Destination('bob@example.com'));

        self::assertFalse(AccountAddresses::belongsTo('@example.com', $catchAll));
        self::assertFalse(AccountAddresses::belongsTo('bob@example.com', $catchAll));
    }

    public function testExtensionPatternEscapesWildcards(): void
    {
        self::assertSame('a!_b+%@my-domain.example', AccountAddresses::extensionPattern(new Account('a_b', new Domain('my-domain.example'))));
    }
}
