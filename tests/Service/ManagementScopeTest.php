<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\Domain;
use App\Repository\AccountRepository;
use App\Repository\DomainRepository;
use App\Security\ManagerPolicy;
use App\Service\ManagementScope;
use App\Tests\DatabaseTrait;
use App\Tests\TestData;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ManagementScopeTest extends KernelTestCase
{
    use DatabaseTrait;

    private TestData $data;

    private ManagementScope $scope;

    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = $this->startDatabase();
        $this->data = (new TestData())->persist($entityManager);

        $accountRepository = $entityManager->getRepository(Account::class);
        $domainRepository = $entityManager->getRepository(Domain::class);
        \assert($accountRepository instanceof AccountRepository && $domainRepository instanceof DomainRepository);
        $this->scope = new ManagementScope($accountRepository, $domainRepository, new ManagerPolicy());
    }

    public function testAdminManagesAllDomains(): void
    {
        self::assertSame([$this->data->exampleCom, $this->data->exampleOrg], $this->scope->getManagedDomains($this->data->admin));
        self::assertTrue($this->scope->managesAnything($this->data->admin));
    }

    public function testDomainManagersManageTheirDomains(): void
    {
        self::assertSame([$this->data->exampleCom], $this->scope->getManagedDomains($this->data->alice));
        self::assertSame([$this->data->exampleOrg], $this->scope->getManagedDomains($this->data->carol));
        self::assertSame([], $this->scope->getManagedLists($this->data->alice));
    }

    public function testListManagerManagesTheirLists(): void
    {
        self::assertSame([], $this->scope->getManagedDomains($this->data->bob));
        self::assertSame([$this->data->team], $this->scope->getManagedLists($this->data->bob));
        self::assertTrue($this->scope->managesAnything($this->data->bob));
    }

    public function testRegularUserManagesNothing(): void
    {
        self::assertFalse($this->scope->managesAnything($this->data->dave));
    }

    public function testStaleManagerEntriesAreIgnored(): void
    {
        $this->data->alice->disable();
        $this->data->bob->disable();

        self::assertSame([], $this->scope->getManagedDomains($this->data->alice));
        self::assertSame([], $this->scope->getManagedLists($this->data->bob));
    }
}
