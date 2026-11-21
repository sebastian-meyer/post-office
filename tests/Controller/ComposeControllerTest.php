<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Tests\DatabaseTrait;
use App\Tests\TestData;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ComposeControllerTest extends WebTestCase
{
    use DatabaseTrait;

    /**
     * Forms use stateless CSRF protection, which accepts requests from the same origin.
     */
    private const array SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    private KernelBrowser $client;

    private TestData $data;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->data = (new TestData())->persist($this->startDatabase());
    }

    public function testDomainPageHasSelection(): void
    {
        $this->client->loginUser($this->data->alice);
        $crawler = $this->client->request('GET', '/manage/domain/example.com/accounts');

        self::assertCount(1, $crawler->filter('input[data-datatable-target="all"]'));
        self::assertCount(1, $crawler->filter('input[data-datatable-row][value="' . $this->data->bob->id . '"]'));
        self::assertCount(1, $crawler->filter('form[action="/manage/domain/example.com/compose"]'));
    }

    public function testDomainComposeShowsMarkedRecipients(): void
    {
        $sendOnly = $this->persist(new Account('noreply', $this->data->exampleCom, AccountType::SendOnly));
        $this->data->info->disable();
        $this->entityManager->flush();

        $crawler = $this->compose($this->data->alice, '/manage/domain/example.com/compose', [
            $this->data->bob->id, $this->data->team->id, $this->data->info->id, $sendOnly->id, $this->data->carol->id
        ]);

        self::assertSame(['bob@example.com', 'team@example.com'], $this->recipients($crawler));
        self::assertSelectorTextContains('.alert-warning', 'info@example.com, noreply@example.com');
        // Without a password from the login (e.g. sessions from before the feature, or in tests), it has to be entered.
        self::assertSelectorExists('input[name="compose[password]"]');
    }

    public function testDomainComposeRequiresDomainManager(): void
    {
        $this->client->loginUser($this->data->alice);
        $this->client->request('GET', '/manage/domain/example.org/compose');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->data->bob);
        $this->client->request('GET', '/manage/domain/example.com/compose');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanWriteToAnyDomain(): void
    {
        $crawler = $this->compose($this->data->admin, '/manage/domain/example.org/compose', [$this->data->carol->id, $this->data->dave->id]);

        self::assertSame(['carol@example.org', 'dave@example.org'], $this->recipients($crawler));
    }

    public function testSendEmailToSeveralRecipients(): void
    {
        $crawler = $this->compose($this->data->alice, '/manage/domain/example.com/compose', [$this->data->bob->id, $this->data->team->id]);
        $this->send($crawler, ['compose[password]' => 'secret']);

        self::assertResponseRedirects('/manage/domain/example.com/accounts', 303);
        self::assertEmailCount(1);

        $event = self::getMailerEvent(0);
        self::assertInstanceOf(MessageEvent::class, $event);
        $email = $event->getMessage();
        self::assertInstanceOf(Email::class, $email);
        // The recipients don't see each other, the email is addressed to the sender.
        self::assertSame('alice@example.com', $email->getFrom()[0]->getAddress());
        self::assertSame(['alice@example.com'], array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo()));
        self::assertSame('Hello', $email->getSubject());
        self::assertSame(
            ['bob@example.com', 'team@example.com', 'alice@example.com'],
            array_map(static fn (Address $address): string => $address->getAddress(), $event->getEnvelope()->getRecipients())
        );

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', '2 recipients');

        // The password has been accepted, so it isn't asked for again.
        $this->compose($this->data->alice, '/manage/domain/example.com/compose', [$this->data->bob->id]);
        self::assertSelectorNotExists('input[name="compose[password]"]');
    }

    public function testEmailToSingleRecipientIsAddressedToThem(): void
    {
        $crawler = $this->compose($this->data->bob, '/manage/list/' . $this->data->team->id . '/compose', ['team@example.com']);
        $this->send($crawler, ['compose[password]' => 'secret']);

        self::assertResponseRedirects('/manage/list/' . $this->data->team->id, 303);
        $email = self::getMailerMessage(0);
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('team@example.com', $email->getTo()[0]->getAddress());
    }

    public function testRecipientsCanBeUnmarked(): void
    {
        $crawler = $this->compose($this->data->alice, '/manage/domain/example.com/compose', [$this->data->bob->id, $this->data->team->id]);
        $form = $crawler->selectButton('Send')->form([
            'compose[subject]' => 'Hello',
            'compose[body]' => 'Hello world',
            'compose[password]' => 'secret'
        ]);
        $checkboxes = $form['compose[recipients]'];
        self::assertIsArray($checkboxes);

        foreach ($checkboxes as $checkbox) {
            self::assertInstanceOf(ChoiceFormField::class, $checkbox);

            if ($checkbox->availableOptionValues() === [(string) $this->data->bob->id]) {
                $checkbox->untick();
            }
        }

        $this->client->submit($form, [], self::SAME_ORIGIN);

        self::assertResponseRedirects();
        $event = self::getMailerEvent(0);
        self::assertSame('team@example.com', $event->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function testRecipientsOutsideTheScopeAreRejected(): void
    {
        $this->client->loginUser($this->data->alice);
        $this->client->request('POST', '/manage/domain/example.com/compose', ['compose' => [
            'recipients' => [(string) $this->data->carol->id],
            'subject' => 'Hello',
            'body' => 'Hello world',
            'password' => 'secret'
        ]], [], self::SAME_ORIGIN);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    public function testListManagerWritesToMembers(): void
    {
        // A member which is a local account that cannot receive emails is skipped.
        $this->data->dave->disable();
        $this->entityManager->flush();

        $crawler = $this->compose($this->data->bob, '/manage/list/' . $this->data->team->id . '/compose', [
            'bob@example.com', 'dave@example.org', 'external@example.net', 'stranger@example.net'
        ]);

        self::assertSame(['bob@example.com', 'external@example.net'], $this->recipients($crawler));
        self::assertSelectorTextContains('.alert-warning', 'dave@example.org');
    }

    public function testMembersOnlyListIsSkippedForNonMembers(): void
    {
        $this->data->team->setListOptions(false, true);
        $this->entityManager->flush();

        // The administrator isn't a member, so the mail server would reject the email to the list.
        $crawler = $this->compose($this->data->admin, '/manage/domain/example.com/compose', [$this->data->bob->id, $this->data->team->id]);
        self::assertSame(['bob@example.com'], $this->recipients($crawler));
        self::assertSelectorTextContains('.alert-warning', 'team@example.com');

        $crawler = $this->client->request('GET', '/manage/list/' . $this->data->team->id);
        self::assertCount(0, $crawler->filter('a[href^="/manage/list/' . $this->data->team->id . '/compose"]'));

        // Bob is a member.
        $crawler = $this->compose($this->data->bob, '/manage/list/' . $this->data->team->id . '/compose', ['team@example.com']);
        self::assertSame(['team@example.com'], $this->recipients($crawler));
    }

    public function testListPageLinksToComposer(): void
    {
        $this->client->loginUser($this->data->bob);
        $crawler = $this->client->request('GET', '/manage/list/' . $this->data->team->id);

        self::assertCount(1, $crawler->filter('a[href^="/manage/list/' . $this->data->team->id . '/compose?selection"]'));
        self::assertCount(1, $crawler->filter('form[action="/manage/list/' . $this->data->team->id . '/compose"]'));
        self::assertCount(4, $crawler->filter('input[data-datatable-row]'));
    }

    public function testListComposeRequiresListManager(): void
    {
        $this->client->loginUser($this->data->dave);
        $this->client->request('GET', '/manage/list/' . $this->data->team->id . '/compose');

        self::assertResponseStatusCodeSame(403);
    }

    public function testPasswordFromLoginIsUsed(): void
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);
        $this->data->alice->setPassword($hasher->hashPassword($this->data->alice, 'correct horse battery staple'));
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form')->form([
            '_username' => 'alice@example.com',
            '_password' => 'correct horse battery staple'
        ]), [], self::SAME_ORIGIN);
        self::assertResponseRedirects();

        $crawler = $this->client->request('POST', '/manage/domain/example.com/compose', ['selection' => [(string) $this->data->bob->id]]);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[name="compose[password]"]');

        $this->send($crawler, []);
        self::assertResponseRedirects('/manage/domain/example.com/accounts', 303);
        self::assertEmailCount(1);
    }

    /**
     * Opens the composer with the given selection (as submitted by the table on the domain or list page).
     *
     * @param list<int|string> $selection The marked values.
     */
    private function compose(Account $user, string $uri, array $selection): Crawler
    {
        $this->client->loginUser($user);
        $crawler = $this->client->request('POST', $uri, ['selection' => array_map(strval(...), $selection)]);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * Fills in and submits the compose form.
     *
     * @param array<string, string> $values Additional form values.
     */
    private function send(Crawler $crawler, array $values): void
    {
        $form = $crawler->selectButton('Send')->form(['compose[subject]' => 'Hello', 'compose[body]' => 'Hello world', ...$values]);
        $this->client->submit($form, [], self::SAME_ORIGIN);
    }

    /**
     * Returns the marked recipients shown in the form.
     *
     * @return list<string> The email addresses.
     */
    private function recipients(Crawler $crawler): array
    {
        return array_values($crawler->filter('input[name="compose[recipients][]"]:checked')->each(
            static fn (Crawler $checkbox): string => trim($checkbox->closest('.form-check')?->text() ?? '')
        ));
    }

    private function persist(Account $account): Account
    {
        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }
}
