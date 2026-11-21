<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Destination;
use App\Service\AddressDeliverability;
use App\Tests\DatabaseTrait;
use App\Tests\TestData;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AddressDeliverabilityTest extends KernelTestCase
{
    use DatabaseTrait;

    private TestData $data;

    private AddressDeliverability $deliverability;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->data = (new TestData())->persist($this->startDatabase());

        $deliverability = self::getContainer()->get(AddressDeliverability::class);
        \assert($deliverability instanceof AddressDeliverability);
        $this->deliverability = $deliverability;
    }

    public function testAddressesOfHostedDomains(): void
    {
        $alice = $this->data->alice;

        self::assertTrue($this->deliverability->accepts('bob@example.com', $alice));
        self::assertTrue($this->deliverability->accepts('BOB+news@Example.com', $alice));
        self::assertTrue($this->deliverability->accepts('team+announce@example.com', $alice));
        // Unknown addresses of hosted domains are rejected without catch-all address.
        self::assertFalse($this->deliverability->accepts('typo@example.com', $alice));
        // Addresses of other domains are relayed.
        self::assertTrue($this->deliverability->accepts('someone@example.net', $alice));
    }

    public function testDisabledAccountsAndCatchAll(): void
    {
        $this->data->bob->disable();
        $sendOnly = new Account('noreply', $this->data->exampleCom, AccountType::SendOnly);
        $this->entityManager->persist($sendOnly);
        $this->entityManager->flush();

        self::assertFalse($this->deliverability->accepts('bob+news@example.com', $this->data->alice));

        $destination = new Destination('catchall@example.net');
        $catchAll = new Account(null, $this->data->exampleCom, AccountType::Alias, null, $destination);
        $this->entityManager->persist($destination);
        $this->entityManager->persist($catchAll);
        $this->entityManager->flush();

        // Disabled accounts and unknown addresses go to the catch-all address, send-only addresses are rejected.
        self::assertTrue($this->deliverability->accepts('bob@example.com', $this->data->alice));
        self::assertTrue($this->deliverability->accepts('typo@example.com', $this->data->alice));
        self::assertFalse($this->deliverability->accepts('noreply+x@example.com', $this->data->alice));
    }

    public function testMembersOnlyListWithExtension(): void
    {
        $this->data->team->setListOptions(false, true);
        $this->entityManager->flush();

        self::assertTrue($this->deliverability->accepts('team+x@example.com', $this->data->bob));
        self::assertFalse($this->deliverability->accepts('team+x@example.com', $this->data->carol));
    }
}
