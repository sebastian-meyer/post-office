<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\MembershipManager;
use App\Tests\DatabaseTrait;
use App\Tests\ServiceTestTrait;
use App\Tests\TestData;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MembershipManagerTest extends KernelTestCase
{
    use DatabaseTrait;
    use ServiceTestTrait;

    private EntityManagerInterface $em;

    private TestData $data;

    private MembershipManager $manager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = $this->startDatabase();
        $this->data = (new TestData())->persist($this->em);
        $this->manager = $this->membershipManager($this->em);
    }

    public function testAddAndRemoveMembers(): void
    {
        $this->manager->addMember($this->data->team, 'new@example.net');
        self::assertCount(5, $this->data->team->destinations);

        // Adding again (in different case) has no effect
        $this->manager->addMember($this->data->team, 'NEW@example.net');
        self::assertCount(5, $this->data->team->destinations);

        // Removing is case-insensitive
        $this->manager->removeMember($this->data->team, 'New@Example.net');
        self::assertCount(4, $this->data->team->destinations);

        // Removing a non-member has no effect
        $this->manager->removeMember($this->data->team, 'nobody@example.net');
        self::assertCount(4, $this->data->team->destinations);
    }

    public function testMembersCanBeAddedToAnEmptyList(): void
    {
        foreach (['alice@example.com', 'bob@example.com', 'dave@example.org', 'external@example.net'] as $email) {
            $this->manager->removeMember($this->data->team, $email);
        }

        self::assertTrue($this->data->team->isMailingList());
        $this->manager->addMember($this->data->team, 'alice@example.com');
        self::assertCount(1, $this->data->team->destinations);
    }

    public function testAppointAndRemoveManagers(): void
    {
        $this->manager->addDomainManager($this->data->exampleCom, 'bob@example.com');
        self::assertTrue($this->data->exampleCom->isManagedBy($this->data->bob));

        $this->manager->addListManager($this->data->team, 'alice@example.com');
        self::assertTrue($this->data->team->managers->contains($this->data->alice));

        $this->manager->removeDomainManager($this->data->exampleCom, $this->data->bob);
        $this->manager->removeListManager($this->data->team, $this->data->alice);
        self::assertFalse($this->data->exampleCom->isManagedBy($this->data->bob));
        self::assertFalse($this->data->team->managers->contains($this->data->alice));
    }

    public function testManagerMustBeMailboxOfSameDomain(): void
    {
        $this->expectException(LogicException::class);
        $this->manager->addListManager($this->data->team, 'dave@example.org');
    }

    public function testUnknownManager(): void
    {
        $this->expectException(LogicException::class);
        $this->manager->addDomainManager($this->data->exampleCom, 'nobody@example.com');
    }
}
