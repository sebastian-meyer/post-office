<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Account;
use App\Entity\Destination;
use App\Entity\Domain;
use App\Repository\AccountRepository;
use App\Repository\DestinationRepository;
use App\Repository\DomainRepository;
use App\Service\AccountManager;
use App\Service\MembershipManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates the application services for kernel tests.
 *
 * Services that are not used by any controller yet are removed from the container, so they are created here.
 */
trait ServiceTestTrait
{
    private function membershipManager(EntityManagerInterface $entityManager): MembershipManager
    {
        return new MembershipManager(
            $entityManager,
            $this->accountRepository($entityManager),
            $this->domainRepository($entityManager),
            $this->destinationRepository($entityManager)
        );
    }

    private function accountManager(EntityManagerInterface $entityManager): AccountManager
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);

        return new AccountManager(
            $this->accountRepository($entityManager),
            $this->destinationRepository($entityManager),
            $this->membershipManager($entityManager),
            $hasher
        );
    }

    private function accountRepository(EntityManagerInterface $entityManager): AccountRepository
    {
        $repository = $entityManager->getRepository(Account::class);
        \assert($repository instanceof AccountRepository);

        return $repository;
    }

    private function domainRepository(EntityManagerInterface $entityManager): DomainRepository
    {
        $repository = $entityManager->getRepository(Domain::class);
        \assert($repository instanceof DomainRepository);

        return $repository;
    }

    private function destinationRepository(EntityManagerInterface $entityManager): DestinationRepository
    {
        $repository = $entityManager->getRepository(Destination::class);
        \assert($repository instanceof DestinationRepository);

        return $repository;
    }
}
