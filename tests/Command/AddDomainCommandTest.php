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

use App\Entity\Domain;
use App\Tests\DatabaseTrait;
use App\Tests\ServiceTestTrait;
use App\Tests\TestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the console command for adding domains.
 */
final class AddDomainCommandTest extends KernelTestCase
{
    use DatabaseTrait;
    use ServiceTestTrait;

    private EntityManagerInterface $em;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->em = $this->startDatabase();
        (new TestData())->persist($this->em);
        $this->tester = new CommandTester((new Application($kernel))->find('app:domain:add'));
    }

    public function testAddsDomain(): void
    {
        $status = $this->tester->execute(['domain' => ' Example.NET ']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('example.net', $this->tester->getDisplay());
        self::assertInstanceOf(Domain::class, $this->domainRepository($this->em)->find('example.net'));
    }

    public function testRejectsExistingDomain(): void
    {
        $status = $this->tester->execute(['domain' => 'example.com']);

        self::assertSame(Command::FAILURE, $status);
        self::assertCount(2, $this->domainRepository($this->em)->findAll());
    }

    public function testRejectsInvalidDomain(): void
    {
        $status = $this->tester->execute(['domain' => 'not a domain']);

        self::assertSame(Command::FAILURE, $status);
        self::assertCount(2, $this->domainRepository($this->em)->findAll());
    }
}
