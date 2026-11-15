<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Account;
use App\Security\AdminAccounts;
use App\Tests\TestData;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class AdminAccountsTest extends TestCase
{
    public function testAdminsAreIdentifiedByTheirIds(): void
    {
        $data = new TestData();
        $id = new ReflectionProperty(Account::class, 'id');
        $id->setValue($data->admin, 1);
        $id->setValue($data->alice, 2);

        $adminAccounts = new AdminAccounts(['1', 'invalid', '']);

        self::assertTrue($adminAccounts->isAdmin($data->admin));
        self::assertFalse($adminAccounts->isAdmin($data->alice));
    }

    public function testNewAccountsAreNoAdmins(): void
    {
        self::assertFalse((new AdminAccounts(['1']))->isAdmin((new TestData())->admin));
    }
}
