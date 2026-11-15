<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;

/**
 * Provides a clean test database for kernel and web test cases.
 *
 * The schema is recreated from the entity mapping once per test class (there are no migrations yet), and every test
 * runs inside a transaction which is rolled back afterwards.
 */
trait DatabaseTrait
{
    private static bool $schemaCreated = false;

    private EntityManagerInterface $entityManager;

    /**
     * Starts a transaction on the test database. Call this after booting the kernel or creating the client.
     */
    protected function startDatabase(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        if (!self::$schemaCreated) {
            $database = $entityManager->getConnection()->getDatabase();

            if ($database === null || !str_ends_with($database, '_test')) {
                throw new LogicException(\sprintf('Refusing to recreate the schema of database "%s".', (string) $database));
            }

            // Drop everything (not only the mapped tables), so even a half-created schema (e.g. after an aborted run)
            // doesn't break the tests.
            $schemaTool = new SchemaTool($entityManager);
            $schemaTool->dropDatabase();
            $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
            self::$schemaCreated = true;
        }

        // Rolled back transactions don't reset auto-increment values, so reset them to get the same IDs in every test
        // (e.g. ID 1 for the administrator, see TestData and ADMIN_IDS in .env.test). This must happen outside of the
        // transaction, since DDL statements commit implicitly.
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isIdGeneratorIdentity()) {
                $entityManager->getConnection()->executeStatement(\sprintf('ALTER TABLE %s AUTO_INCREMENT = 1', $metadata->getTableName()));
            }
        }

        $entityManager->getConnection()->beginTransaction();

        return $entityManager;
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $connection = $this->entityManager->getConnection();

            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            $this->entityManager->close();
        }

        parent::tearDown();
    }
}
