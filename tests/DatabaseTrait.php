<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Provides a clean test database for kernel and web test cases.
 *
 * The schema is recreated by running all migrations once per test class, and every test runs inside a transaction
 * which is rolled back afterwards. MigrationsTest makes sure the migrations match the entity mapping.
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

            // Drop everything (not only the mapped tables, but also the migrations' version table), so even a
            // half-created schema (e.g. after an aborted run) doesn't break the tests.
            (new SchemaTool($entityManager))->dropDatabase();
            $this->migrateDatabase();
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

    /**
     * Runs all migrations on the test database.
     */
    private function migrateDatabase(): void
    {
        $kernel = self::$kernel ?? throw new LogicException('The kernel must be booted before migrating the database.');
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $input = new ArrayInput(['command' => 'doctrine:migrations:migrate', '--no-interaction' => true]);
        $input->setInteractive(false);

        if ($application->run($input, $output) !== 0) {
            throw new LogicException(\sprintf('Migrating the test database failed: %s', $output->fetch()));
        }
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
