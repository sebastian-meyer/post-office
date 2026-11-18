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

namespace App\Tests\Command;

use App\Entity\Account;
use App\Tests\DatabaseTrait;
use App\Tests\ServiceTestTrait;
use App\Tests\TestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests the console command for adding accounts.
 */
final class AddAccountCommandTest extends KernelTestCase
{
    use DatabaseTrait;
    use ServiceTestTrait;

    private const string PASSWORD = 'correct horse battery staple';

    private EntityManagerInterface $em;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->em = $this->startDatabase();
        (new TestData())->persist($this->em);
        $this->tester = new CommandTester((new Application($kernel))->find('app:account:add'));
    }

    public function testAddsMailboxAndPrintsId(): void
    {
        $this->tester->setInputs([self::PASSWORD, self::PASSWORD]);
        $status = $this->tester->execute(['email' => 'erin@Example.com', '--name' => 'Erin', '--quota' => '250']);

        self::assertSame(Command::SUCCESS, $status);

        $account = $this->accountRepository($this->em)->findOneByEmail('erin@example.com');
        self::assertInstanceOf(Account::class, $account);
        self::assertTrue($account->isMailbox());
        self::assertSame('Erin', $account->label);
        self::assertSame(250, $account->quota);
        self::assertStringContainsString(\sprintf('with ID %d', $account->id), $this->tester->getDisplay());

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);
        self::assertTrue($hasher->isPasswordValid($account, self::PASSWORD));
    }

    public function testAddsSendOnlyAddress(): void
    {
        $this->tester->setInputs([self::PASSWORD, self::PASSWORD]);
        $status = $this->tester->execute(['email' => 'noreply@example.org', '--send-only' => true]);

        self::assertSame(Command::SUCCESS, $status);

        $account = $this->accountRepository($this->em)->findOneByEmail('noreply@example.org');
        self::assertInstanceOf(Account::class, $account);
        self::assertTrue($account->isSendOnly());
    }

    public function testRejectsUnknownDomain(): void
    {
        $status = $this->tester->execute(['email' => 'erin@example.net']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('app:domain:add example.net', $this->tester->getDisplay());
    }

    public function testRejectsExistingAddressBeforeAskingForPassword(): void
    {
        $status = $this->tester->execute(['email' => 'alice@example.com']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringNotContainsString('Password', $this->tester->getDisplay());
    }

    public function testRejectsMismatchingPasswords(): void
    {
        $this->tester->setInputs([self::PASSWORD, 'something else']);
        $status = $this->tester->execute(['email' => 'erin@example.com']);

        self::assertSame(Command::FAILURE, $status);
        self::assertNull($this->accountRepository($this->em)->findOneByEmail('erin@example.com'));
    }

    public function testRejectsWeakPassword(): void
    {
        $this->tester->setInputs(['secret', 'secret']);
        $status = $this->tester->execute(['email' => 'erin@example.com']);

        self::assertSame(Command::FAILURE, $status);
        self::assertNull($this->accountRepository($this->em)->findOneByEmail('erin@example.com'));
    }

    public function testRejectsQuotaForSendOnlyAddress(): void
    {
        $status = $this->tester->execute(['email' => 'noreply@example.org', '--send-only' => true, '--quota' => '10']);

        self::assertSame(Command::FAILURE, $status);
    }

    public function testAddsAliasWithoutAskingForPassword(): void
    {
        $status = $this->tester->execute(
            ['email' => 'sales@example.com', '--name' => 'Sales', '--alias' => 'alice@example.com'],
            ['interactive' => false]
        );

        self::assertSame(Command::SUCCESS, $status);

        $account = $this->accountRepository($this->em)->findOneByEmail('sales@example.com');
        self::assertInstanceOf(Account::class, $account);
        self::assertTrue($account->isAlias());
        self::assertNull($account->password);
        self::assertSame('alice@example.com', (string) $account->destinations->first());

        $display = $this->tester->getDisplay();
        self::assertStringContainsString(\sprintf('alias sales@example.com has been created with ID %d', $account->id), $display);
        self::assertStringNotContainsString('Password', $display);
        self::assertStringNotContainsString('ADMIN_IDS', $display);
    }

    public function testAddsCatchAllAlias(): void
    {
        $status = $this->tester->execute(['email' => '@example.org', '--alias' => 'carol@example.org']);

        self::assertSame(Command::SUCCESS, $status);

        $account = $this->accountRepository($this->em)->findOneBy(['username' => null, 'domain' => 'example.org']);
        self::assertInstanceOf(Account::class, $account);
        self::assertTrue($account->isCatchAll());
        self::assertStringContainsString('catch-all alias @example.org', $this->tester->getDisplay());

        // There can only be one catch-all address per domain.
        self::assertSame(Command::FAILURE, $this->tester->execute(['email' => '@example.org', '--alias' => 'dave@example.org']));
    }

    public function testRejectsCatchAllMailbox(): void
    {
        $status = $this->tester->execute(['email' => '@example.org']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringNotContainsString('Password', $this->tester->getDisplay());
    }

    public function testRejectsInvalidAliasDestination(): void
    {
        $status = $this->tester->execute(['email' => 'sales@example.com', '--alias' => 'not an address']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('alias:', $this->tester->getDisplay());
        self::assertNull($this->accountRepository($this->em)->findOneByEmail('sales@example.com'));
    }

    public function testRejectsConflictingOptions(): void
    {
        self::assertSame(Command::FAILURE, $this->tester->execute(['email' => 'sales@example.com', '--alias' => 'alice@example.com', '--send-only' => true]));
        self::assertSame(Command::FAILURE, $this->tester->execute(['email' => 'sales@example.com', '--alias' => 'alice@example.com', '--quota' => '10']));
        self::assertNull($this->accountRepository($this->em)->findOneByEmail('sales@example.com'));
    }

    public function testRequiresInteraction(): void
    {
        $status = $this->tester->execute(['email' => 'erin@example.com'], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $status);
        self::assertNull($this->accountRepository($this->em)->findOneByEmail('erin@example.com'));
    }
}
