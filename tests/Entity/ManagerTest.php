<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Tests\TestData;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ManagerTest extends TestCase
{
    public function testDomainAcceptsOnlyEnabledMailboxesOfItself(): void
    {
        $data = new TestData();

        self::assertTrue($data->exampleCom->acceptsManager($data->bob));
        self::assertFalse($data->exampleCom->acceptsManager($data->carol), 'mailbox of other domain');
        self::assertFalse($data->exampleCom->acceptsManager($data->info), 'alias');
        self::assertFalse($data->exampleCom->acceptsManager($data->team), 'mailing list');

        $data->bob->disable();
        self::assertFalse($data->exampleCom->acceptsManager($data->bob), 'disabled mailbox');
    }

    public function testDomainRejectsIneligibleManager(): void
    {
        $data = new TestData();

        $this->expectException(LogicException::class);
        $data->exampleCom->addManager($data->carol);
    }

    public function testListRejectsIneligibleManager(): void
    {
        $data = new TestData();

        $this->expectException(LogicException::class);
        $data->team->addManager($data->dave);
    }

    public function testOnlyListsHaveManagers(): void
    {
        $data = new TestData();

        $this->expectException(LogicException::class);
        $data->info->addManager($data->alice);
    }

    public function testConvertingListRemovesManagers(): void
    {
        $data = new TestData();
        self::assertCount(1, $data->team->managers);

        $data->team->makeSendOnly();

        self::assertCount(0, $data->team->managers);
    }

    public function testDomainManagerStatus(): void
    {
        $data = new TestData();

        self::assertTrue($data->exampleCom->isManagedBy($data->alice));
        self::assertFalse($data->exampleCom->isManagedBy($data->bob));
        self::assertFalse($data->exampleOrg->isManagedBy($data->alice));
    }
}
