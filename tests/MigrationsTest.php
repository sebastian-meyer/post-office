<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Makes sure the migrations create exactly the schema of the entity mapping.
 *
 * If this fails after changing an entity, create a migration with "bin/console make:migration".
 */
final class MigrationsTest extends KernelTestCase
{
    use DatabaseTrait;

    public function testMigrationsMatchEntityMapping(): void
    {
        self::bootKernel();
        $entityManager = $this->startDatabase();

        // The migrations' own version table isn't part of the mapping.
        $differences = array_filter(
            (new SchemaValidator($entityManager))->getUpdateSchemaList(),
            static fn (string $sql): bool => $sql !== 'DROP TABLE doctrine_migration_versions'
        );

        self::assertSame([], array_values($differences));
    }

    public function testDefaultTlsPoliciesAreInstalled(): void
    {
        $policies = $this->startDatabase()->getConnection()
            ->fetchAllKeyValue('SELECT domain, CONCAT(policy, \' \', params) FROM tlspolicies WHERE enabled = 1');

        self::assertCount(18, $policies);
        self::assertSame('dane-only ', $policies['gmx.de'] ?? null);
        self::assertSame('secure match=.google.com', $policies['gmail.com'] ?? null);
    }
}
