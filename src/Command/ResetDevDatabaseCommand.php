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

namespace App\Command;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Blacklist;
use App\Entity\Destination;
use App\Entity\Domain;
use App\Entity\TlsPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Recreates the database schema and loads sample data for development.
 *
 * Until version 1.0 the schema is not managed by migrations, so this command is the way to apply schema changes to
 * the development database. It is only available in the "dev" environment.
 */
#[AsCommand(name: 'app:dev:reset-database', description: 'Drops and recreates the database schema and loads sample data (dev only).')]
#[When(env: 'dev')]
final class ResetDevDatabaseCommand
{
    /**
     * The password for all sample accounts that are allowed to log in.
     */
    private const string PASSWORD = 'post-office';

    /**
     * Cache of destinations, so each address is only persisted once.
     *
     * @var array<string, Destination>
     */
    private array $destinations = [];

    /**
     * Executes the command.
     *
     * @param SymfonyStyle $io The console input/output helper.
     * @param bool $force Whether to actually drop all data.
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Actually drop all existing data and recreate the schema.')] bool $force = false
    ): int {
        if (!$force) {
            $io->warning('This will DROP ALL DATA in the configured database. Run again with --force to proceed.');

            return Command::FAILURE;
        }

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $io->info('Database schema recreated.');

        $this->loadSampleData();

        $io->success('Sample data loaded.');
        $io->table(
            ['Account', 'Role'],
            [
                ['admin@example.com', 'Global admin (ID 1, if ADMIN_IDS=1)'],
                ['alice@example.com', 'Domain manager of example.com'],
                ['bob@example.com', 'List manager of team@example.com'],
                ['carol@example.org', 'Domain manager of example.org'],
                ['dave@example.org', 'Regular user, member of team@example.com']
            ]
        );
        $io->note(\sprintf('All accounts above use the password "%s".', self::PASSWORD));

        return Command::SUCCESS;
    }

    /**
     * Creates the sample domains, accounts, policies and blacklist entries.
     */
    private function loadSampleData(): void
    {
        $exampleCom = new Domain('example.com');
        $exampleOrg = new Domain('example.org');
        $this->entityManager->persist($exampleCom);
        $this->entityManager->persist($exampleOrg);

        // The admin account is created and flushed first, so it gets ID 1.
        $this->createMailbox('admin', $exampleCom, 'Administrator');
        $this->entityManager->flush();

        $alice = $this->createMailbox('alice', $exampleCom, 'Alice');
        $bob = $this->createMailbox('bob', $exampleCom, 'Bob');
        $carol = $this->createMailbox('carol', $exampleOrg, 'Carol');
        $this->createMailbox('dave', $exampleOrg, 'Dave');

        $exampleCom->addManager($alice);
        $exampleOrg->addManager($carol);

        $this->createAccount('noreply', $exampleCom, AccountType::SendOnly, 'No Reply');
        $this->createAccount('info', $exampleCom, AccountType::Alias, 'Info', 'alice@example.com');
        $this->createAccount(null, $exampleOrg, AccountType::Alias, 'Catch-all', 'carol@example.org');

        $team = $this->createAccount(
            'team',
            $exampleCom,
            AccountType::MailingList,
            'Team',
            'alice@example.com',
            'bob@example.com',
            'dave@example.org',
            'external@example.net'
        );
        $team->setReplyToList(true)->setStrictList(true)->addManager($bob);

        $this->createAccount('empty', $exampleOrg, AccountType::MailingList, 'Empty list');

        $this->entityManager->persist((new TlsPolicy('example.net'))->setPolicy('encrypt'));
        $this->entityManager->persist((new Blacklist('spam@example.net'))->setAction('DISCARD'));

        $this->entityManager->flush();
    }

    /**
     * Creates and persists a mailbox with the sample password.
     *
     * @param string $username The username part of the email address.
     * @param Domain $domain The domain part of the email address.
     * @param string $label The label for the account.
     */
    private function createMailbox(string $username, Domain $domain, string $label): Account
    {
        $account = $this->createAccount($username, $domain, AccountType::Mailbox, $label);
        $account->setPassword($this->hasher->hashPassword($account, self::PASSWORD));

        return $account;
    }

    /**
     * Creates and persists an account.
     *
     * @param ?string $username The username part of the email address, or NULL for a catch-all account.
     * @param Domain $domain The domain part of the email address.
     * @param AccountType $type The type of the account.
     * @param string $label The label for the account.
     * @param string ...$destinations The destination email addresses.
     */
    private function createAccount(?string $username, Domain $domain, AccountType $type, string $label, string ...$destinations): Account
    {
        $account = new Account($username, $domain, $type, null, ...array_map($this->getDestination(...), $destinations));
        $account->setLabel($label);
        $this->entityManager->persist($account);

        return $account;
    }

    /**
     * Returns a (persisted) destination for the given email address.
     *
     * @param string $email The destination email address.
     */
    private function getDestination(string $email): Destination
    {
        if (!isset($this->destinations[$email])) {
            $this->destinations[$email] = new Destination($email);
            $this->entityManager->persist($this->destinations[$email]);
        }

        return $this->destinations[$email];
    }

    /**
     * @param EntityManagerInterface $entityManager The entity manager.
     * @param UserPasswordHasherInterface $hasher The password hasher.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $hasher
    ) {
    }
}
