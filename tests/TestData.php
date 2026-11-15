<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Destination;
use App\Entity\Domain;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A small, consistent set of entities for tests (mirrors the sample data of app:dev:reset-database).
 *
 * - admin@example.com: administrator (no other roles); persisted first to get ID 1 (see ADMIN_IDS in .env.test)
 * - alice@example.com: domain manager of example.com, member of team@
 * - bob@example.com: list manager of team@, member of team@
 * - carol@example.org: domain manager of example.org
 * - dave@example.org: regular user, member of team@example.com
 * - info@example.com: alias for alice@example.com
 * - team@example.com: mailing list
 */
final class TestData
{
    public readonly Domain $exampleCom;
    public readonly Domain $exampleOrg;
    public readonly Account $admin;
    public readonly Account $alice;
    public readonly Account $bob;
    public readonly Account $carol;
    public readonly Account $dave;
    public readonly Account $info;
    public readonly Account $team;

    /**
     * @var array<string, Destination>
     */
    private array $destinations = [];

    public function __construct()
    {
        $this->exampleCom = new Domain('example.com');
        $this->exampleOrg = new Domain('example.org');

        $this->admin = (new Account('admin', $this->exampleCom))->setAdmin(true);
        $this->alice = new Account('alice', $this->exampleCom);
        $this->bob = new Account('bob', $this->exampleCom);
        $this->carol = new Account('carol', $this->exampleOrg);
        $this->dave = new Account('dave', $this->exampleOrg);

        $this->exampleCom->addManager($this->alice);
        $this->exampleOrg->addManager($this->carol);

        $this->info = new Account('info', $this->exampleCom, AccountType::Alias, null, $this->destination('alice@example.com'));
        $this->team = new Account(
            'team',
            $this->exampleCom,
            AccountType::MailingList,
            null,
            $this->destination('alice@example.com'),
            $this->destination('bob@example.com'),
            $this->destination('dave@example.org'),
            $this->destination('external@example.net')
        );
        $this->team->addManager($this->bob);
    }

    /**
     * Persists and flushes all entities.
     */
    public function persist(EntityManagerInterface $entityManager): self
    {
        $entities = [
            $this->exampleCom,
            $this->exampleOrg,
            ...array_values($this->destinations),
            $this->admin, // must be the first account (ID 1)
            $this->alice,
            $this->bob,
            $this->carol,
            $this->dave,
            $this->info,
            $this->team
        ];

        foreach ($entities as $entity) {
            $entityManager->persist($entity);
        }

        $entityManager->flush();

        return $this;
    }

    private function destination(string $email): Destination
    {
        return $this->destinations[$email] ??= new Destination($email);
    }
}
