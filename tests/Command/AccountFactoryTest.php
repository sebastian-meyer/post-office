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

use App\Command\AccountFactory;
use App\Form\Model\AliasData;
use App\Form\Model\MailboxData;
use App\Form\Model\MailingListData;
use App\Form\Model\SendOnlyData;
use App\Tests\DatabaseTrait;
use App\Tests\ServiceTestTrait;
use App\Tests\TestData;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests the factory creating accounts from console arguments.
 */
final class AccountFactoryTest extends KernelTestCase
{
    use DatabaseTrait;
    use ServiceTestTrait;

    private TestData $data;

    private AccountFactory $factory;

    protected function setUp(): void
    {
        self::bootKernel();
        $em = $this->startDatabase();
        $this->data = (new TestData())->persist($em);
        $this->factory = new AccountFactory($this->accountManager($em), $this->domainRepository($em));
    }

    public function testCreatesMailboxData(): void
    {
        $data = $this->factory->createData(' erin@Example.COM ', 'Erin', '250', false, null);

        self::assertInstanceOf(MailboxData::class, $data);
        self::assertSame('erin', $data->username);
        self::assertSame($this->data->exampleCom, $data->domain);
        self::assertSame('Erin', $data->label);
        self::assertSame(250, $data->quota);
    }

    public function testCreatesSendOnlyData(): void
    {
        $data = $this->factory->createData('noreply@example.com', '', null, true, null);

        self::assertInstanceOf(SendOnlyData::class, $data);
        self::assertNotInstanceOf(MailboxData::class, $data);
    }

    public function testCreatesAliasData(): void
    {
        $data = $this->factory->createData('sales@example.com', '', null, false, ' alice@example.com ');

        self::assertInstanceOf(AliasData::class, $data);
        self::assertSame('alice@example.com', $data->destination);
    }

    public function testCreatesCatchAllAliasData(): void
    {
        $data = $this->factory->createData('@example.org', '', null, false, 'carol@example.org');

        self::assertInstanceOf(AliasData::class, $data);
        self::assertNull($data->username);
        self::assertSame($this->data->exampleOrg, $data->domain);
    }

    /**
     * @return iterable<string, array{string, ?string, bool, ?string}>
     */
    public static function invalidArguments(): iterable
    {
        yield 'catch-all mailbox' => ['@example.com', null, false, null];
        yield 'missing @' => ['example.com', null, false, null];
        yield 'unknown domain' => ['erin@example.net', null, false, null];
        yield 'quota not a number' => ['erin@example.com', 'lots', false, null];
        yield 'quota for send-only' => ['erin@example.com', '10', true, null];
        yield 'quota for alias' => ['erin@example.com', '10', false, 'alice@example.com'];
        yield 'send-only alias' => ['erin@example.com', null, true, 'alice@example.com'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidArguments')]
    public function testRejectsInvalidArguments(string $email, ?string $quota, bool $sendOnly, ?string $alias): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->factory->createData($email, '', $quota, $sendOnly, $alias);
    }

    public function testSaveRejectsMailingLists(): void
    {
        $data = new MailingListData();
        $data->username = 'staff';
        $data->domain = $this->data->exampleCom;

        $this->expectException(InvalidArgumentException::class);

        $this->factory->save($data);
    }
}
