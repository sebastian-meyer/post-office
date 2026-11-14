<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Form\Model\AliasData;
use App\Form\Model\MailboxData;
use App\Form\Model\MailingListData;
use App\Service\AccountManager;
use App\Tests\DatabaseTrait;
use App\Tests\ServiceTestTrait;
use App\Tests\TestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountManagerTest extends KernelTestCase
{
    use DatabaseTrait;
    use ServiceTestTrait;

    private EntityManagerInterface $em;

    private TestData $data;

    private AccountManager $manager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = $this->startDatabase();
        $this->data = (new TestData())->persist($this->em);
        $this->manager = $this->accountManager($this->em);
    }

    public function testCreateMailboxHashesPassword(): void
    {
        $data = new MailboxData();
        $data->username = 'erin';
        $data->domain = $this->data->exampleCom;
        $data->label = ' Erin ';
        $data->quota = 250;
        $data->plainPassword = 'correct horse battery staple';

        $account = $this->manager->createMailbox($data);
        $this->em->clear();

        $saved = $this->accountRepository($this->em)->findOneByEmail('erin@example.com');
        self::assertInstanceOf(Account::class, $saved);
        self::assertTrue($saved->isMailbox());
        self::assertSame('Erin', $saved->label);
        self::assertSame(250, $saved->quota);
        self::assertNotSame('correct horse battery staple', $saved->password);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);
        self::assertTrue($hasher->isPasswordValid($saved, 'correct horse battery staple'));
        self::assertSame($account->id, $saved->id);
    }

    public function testCreateCatchAllAlias(): void
    {
        $data = new AliasData();
        $data->domain = $this->data->exampleOrg;
        $data->destination = 'dave@example.org';

        $alias = $this->manager->createAlias($data);

        self::assertTrue($alias->isCatchAll());
        self::assertTrue($alias->isAlias());
        self::assertSame('dave@example.org', (string) $alias->destinations->first());
    }

    public function testCreateMailingListWithoutMembers(): void
    {
        $data = new MailingListData();
        $data->username = 'news';
        $data->domain = $this->data->exampleCom;
        $data->strictList = true;

        $list = $this->manager->createMailingList($data);

        self::assertTrue($list->isMailingList());
        self::assertCount(0, $list->destinations);
        self::assertTrue($list->strictList);
        self::assertFalse($list->replyToList);
    }

    public function testUpdateMailboxKeepsPasswordWhenEmpty(): void
    {
        $oldHash = $this->data->dave->password;
        $data = MailboxData::fromAccount($this->data->dave);
        $data->quota = 0;

        $this->manager->updateMailbox($this->data->dave, $data);

        self::assertSame($oldHash, $this->data->dave->password);
        self::assertSame(0, $this->data->dave->quota);
    }

    public function testDisablingMailboxRevokesManagerRoles(): void
    {
        $data = MailboxData::fromAccount($this->data->alice);
        $data->enabled = false;

        $this->manager->updateMailbox($this->data->alice, $data);
        $this->em->clear();

        self::assertSame([], $this->domainRepository($this->em)->findByManager($this->data->alice));
    }

    public function testUpdateAliasChangesDestination(): void
    {
        $data = AliasData::fromAccount($this->data->info);
        self::assertSame('alice@example.com', $data->destination);
        $data->destination = 'bob@example.com';

        $this->manager->updateAlias($this->data->info, $data);

        self::assertCount(1, $this->data->info->destinations);
        self::assertSame('bob@example.com', (string) $this->data->info->destinations->first());
    }

    public function testDeleteManagerAccount(): void
    {
        // bob is list manager of team@ and must be removed from that role
        $this->manager->delete($this->data->bob);
        $this->em->clear();

        $accounts = $this->accountRepository($this->em);
        self::assertNull($accounts->findOneByEmail('bob@example.com'));
        $team = $accounts->findOneByEmail('team@example.com');
        self::assertInstanceOf(Account::class, $team);
        self::assertCount(0, $team->managers);
    }

    public function testDeleteMailingList(): void
    {
        $this->manager->delete($this->data->team);
        $this->em->clear();

        self::assertNull($this->accountRepository($this->em)->findOneByEmail('team@example.com'));
    }
}
