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

            $schemaTool = new SchemaTool($entityManager);
            $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
            self::$schemaCreated = true;
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
