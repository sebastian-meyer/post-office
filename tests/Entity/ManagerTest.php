<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Tests\TestData;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ManagerTest extends TestCase
{
    public function testOnlyEnabledMailboxesCanBeManagers(): void
    {
        $data = new TestData();

        self::assertTrue($data->bob->canBeManager());
        self::assertTrue($data->carol->canBeManager(), 'mailbox of other domain');
        self::assertFalse($data->info->canBeManager(), 'alias');
        self::assertFalse($data->team->canBeManager(), 'mailing list');

        $data->bob->disable();
        self::assertFalse($data->bob->canBeManager(), 'disabled mailbox');
    }

    public function testManagersCanBelongToOtherDomains(): void
    {
        $data = new TestData();

        $data->exampleCom->addManager($data->carol);
        $data->team->addManager($data->dave);

        self::assertTrue($data->exampleCom->isManagedBy($data->carol));
        self::assertTrue($data->team->managers->contains($data->dave));
    }

    public function testDomainRejectsIneligibleManager(): void
    {
        $data = new TestData();

        $this->expectException(LogicException::class);
        $data->exampleCom->addManager($data->info);
    }

    public function testListRejectsIneligibleManager(): void
    {
        $data = new TestData();
        $data->dave->disable();

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
