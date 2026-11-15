<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Tests\DatabaseTrait;
use App\Tests\TestData;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AdminAccountGuardTest extends KernelTestCase
{
    use DatabaseTrait;

    public function testAdminAccountCannotBeDeleted(): void
    {
        self::bootKernel();
        $entityManager = $this->startDatabase();
        $data = (new TestData())->persist($entityManager);

        $this->expectException(LogicException::class);
        $entityManager->remove($data->admin);
    }

    public function testOtherAccountsCanBeDeleted(): void
    {
        self::bootKernel();
        $entityManager = $this->startDatabase();
        $data = (new TestData())->persist($entityManager);

        $entityManager->remove($data->dave);
        $entityManager->flush();

        self::assertFalse($entityManager->contains($data->dave));
    }
}
