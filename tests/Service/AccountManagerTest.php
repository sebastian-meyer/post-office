<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Form\Model\AliasData;
use App\Form\Model\MailboxData;
use App\Form\Model\MailingListData;
use App\Form\Model\SendOnlyData;
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

    public function testDeleteRemovesAddressFromMailingLists(): void
    {
        // dave@example.org is a member of team@example.com (in another domain)
        self::assertSame([], $this->manager->delete($this->data->dave));
        $this->em->clear();

        $team = $this->accountRepository($this->em)->findOneByEmail('team@example.com');
        self::assertInstanceOf(Account::class, $team);
        self::assertSame(
            ['alice@example.com', 'bob@example.com', 'external@example.net'],
            array_map(strval(...), $team->destinations->toArray())
        );
    }

    public function testDeleteAlsoDeletesCatchAllListWithoutMembers(): void
    {
        $catchAll = new Account(
            null,
            $this->data->exampleOrg,
            AccountType::MailingList,
            null,
            $this->destinationRepository($this->em)->getEntity('bob@example.com')
        );
        $this->em->persist($catchAll);
        $this->em->flush();

        self::assertSame(
            [['address' => '@example.org', 'destination' => 'bob@example.com']],
            $this->manager->delete($this->data->bob)
        );
        $this->em->clear();

        $accounts = $this->accountRepository($this->em);
        self::assertNull($accounts->findOneByEmail('@example.org'));
        $team = $accounts->findOneByEmail('team@example.com');
        self::assertInstanceOf(Account::class, $team);
        self::assertCount(3, $team->destinations);
    }

    public function testDeleteAlsoDeletesForwardingAliases(): void
    {
        // contact@ -> info@ -> alice@
        $contact = $this->alias('contact', 'info@example.com');

        self::assertSame(
            [
                ['address' => 'info@example.com', 'destination' => 'alice@example.com'],
                ['address' => 'contact@example.com', 'destination' => 'info@example.com']
            ],
            $this->manager->delete($this->data->alice)
        );
        $this->em->clear();

        $accounts = $this->accountRepository($this->em);
        self::assertNull($accounts->findOneByEmail('info@example.com'));
        self::assertNull($accounts->findOneByEmail((string) $contact));
        // Mailing lists without a catch-all address are kept, even without members.
        self::assertInstanceOf(Account::class, $accounts->findOneByEmail('team@example.com'));
    }

    public function testDeleteStopsAtCircularAliases(): void
    {
        $ping = $this->alias('ping', 'pong@example.com');
        $this->alias('pong', 'ping@example.com');

        self::assertSame(
            [['address' => 'pong@example.com', 'destination' => 'ping@example.com']],
            $this->manager->delete($ping)
        );
        $this->em->clear();

        self::assertNull($this->accountRepository($this->em)->findOneByEmail('pong@example.com'));
    }

    public function testCreateAndUpdateSendOnly(): void
    {
        $data = new SendOnlyData();
        $data->username = 'newsletter';
        $data->domain = $this->data->exampleCom;
        $data->plainPassword = 'correct horse battery staple';

        $account = $this->manager->createSendOnly($data);
        self::assertTrue($account->isSendOnly());
        self::assertNull($account->quota);
        $hash = $account->password;
        self::assertNotNull($hash);

        $data = SendOnlyData::fromAccount($account);
        $data->label = 'Newsletter';
        $this->manager->updateSendOnly($account, $data);

        self::assertSame('Newsletter', $account->label);
        self::assertSame($hash, $account->password);
    }

    public function testDisablingWithToggleRevokesManagerRoles(): void
    {
        $this->manager->setEnabled($this->data->alice, false);
        self::assertFalse($this->data->alice->enabled);
        $this->em->clear();
        self::assertSame([], $this->domainRepository($this->em)->findByManager($this->data->alice));
    }

    public function testDeleteMailingList(): void
    {
        $this->manager->delete($this->data->team);
        $this->em->clear();

        self::assertNull($this->accountRepository($this->em)->findOneByEmail('team@example.com'));
    }

    private function alias(string $username, string $destination): Account
    {
        $alias = new Account(
            $username,
            $this->data->exampleCom,
            AccountType::Alias,
            null,
            $this->destinationRepository($this->em)->getEntity($destination)
        );
        $this->em->persist($alias);
        $this->em->flush();

        return $alias;
    }
}
