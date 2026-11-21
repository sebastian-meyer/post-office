<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Destination;
use App\Form\Model\MailboxData;
use App\Form\Model\ManagerData;
use App\Form\Type\AliasType;
use App\Form\Type\MailboxType;
use App\Form\Type\MailingListType;
use App\Form\Type\ManagerType;
use App\Tests\DatabaseTrait;
use App\Tests\TestData;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;

final class FormValidationTest extends KernelTestCase
{
    use DatabaseTrait;

    private TestData $data;

    private FormFactoryInterface $forms;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->data = (new TestData())->persist($this->startDatabase());

        $forms = self::getContainer()->get(FormFactoryInterface::class);
        \assert($forms instanceof FormFactoryInterface);
        $this->forms = $forms;
    }

    public function testNewMailboxIsValid(): void
    {
        $form = $this->submitNew(MailboxType::class, ['username' => 'erin', 'quota' => '100', 'plainPassword' => 'correct horse battery staple']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
    }

    public function testExistingAddressIsRejected(): void
    {
        $form = $this->submitNew(MailboxType::class, ['username' => 'alice', 'quota' => '100', 'plainPassword' => 'correct horse battery staple']);

        self::assertSame(['accountAlreadyExists'], $this->errors($form, 'username'));
    }

    public function testMailboxRequiresUsernameAndStrongPassword(): void
    {
        $form = $this->submitNew(MailboxType::class, ['username' => '', 'quota' => '100', 'plainPassword' => 'secret']);

        self::assertSame(['usernameRequired'], $this->errors($form, 'username'));
        self::assertNotSame([], $this->errors($form, 'plainPassword'));
    }

    public function testUsernamesMustWorkWithTheMailServer(): void
    {
        foreach (['a..b', '.a', 'a.', "o'brien", 'a+b'] as $username) {
            $form = $this->submitNew(MailboxType::class, ['username' => $username, 'quota' => '100', 'plainPassword' => 'correct horse battery staple']);
            self::assertSame(['invalidUsername'], $this->errors($form, 'username'), $username);
        }

        $form = $this->submitNew(MailboxType::class, ['username' => 'John.Doe-2_x', 'quota' => '100', 'plainPassword' => 'correct horse battery staple']);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
    }

    public function testPasswordsLongerThan72BytesAreRejected(): void
    {
        // 70 characters, but 74 bytes in UTF-8.
        $form = $this->submitNew(MailboxType::class, ['username' => 'erin', 'quota' => '100', 'plainPassword' => str_repeat('a', 66) . 'äöüß']);

        self::assertContains('passwordTooLong', $this->errors($form, 'plainPassword'));
    }

    public function testAddressesAreValidatedLikeTheEntitiesDo(): void
    {
        $form = $this->submitNew(AliasType::class, ['username' => 'shop', 'destination' => 'a..b@example.com']);

        self::assertContains('invalidEmail', $this->errors($form, 'destination'));
        self::assertContains('invalidEmail', $this->errors($this->submitManager('.bob@example.com'), 'email'));
    }

    public function testMailingListRequiresUsername(): void
    {
        $form = $this->submitNew(MailingListType::class, ['username' => '']);

        self::assertFalse($form->get('username')->isValid());
    }

    public function testCatchAllAliasIsAllowedOnlyOnce(): void
    {
        $first = $this->submitNew(AliasType::class, ['username' => '', 'destination' => 'alice@example.com']);
        self::assertTrue($first->isValid(), (string) $first->getErrors(true));

        $destination = $this->data->info->destinations->first();
        self::assertInstanceOf(Destination::class, $destination);
        $catchAll = new Account(null, $this->data->exampleCom, AccountType::Alias, null, $destination);
        $this->entityManager->persist($catchAll);
        $this->entityManager->flush();

        $second = $this->submitNew(AliasType::class, ['username' => '', 'destination' => 'bob@example.com']);
        self::assertSame(['accountAlreadyExists'], $this->errors($second, 'username'));
    }

    public function testEditingIgnoresUsernameAndAllowsEmptyPassword(): void
    {
        $form = $this->forms->create(MailboxType::class, MailboxData::fromAccount($this->data->alice), ['csrf_protection' => false]);
        $form->submit(['label' => 'Alice', 'enabled' => '1', 'quota' => '200', 'plainPassword' => '']);

        self::assertFalse($form->has('username'));
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
    }

    public function testManagerEligibility(): void
    {
        self::assertSame([], $this->errors($this->submitManager('bob@example.com'), 'email'));
        self::assertSame([], $this->errors($this->submitManager('dave@example.org'), 'email'));
        self::assertSame(['managerNotEligible'], $this->errors($this->submitManager('info@example.com'), 'email'));
        self::assertSame(['managerNotFound'], $this->errors($this->submitManager('nobody@example.com'), 'email'));
    }

    /**
     * @param class-string<FormTypeInterface<mixed>> $type
     * @param array<string, string> $values
     *
     * @return FormInterface<mixed>
     */
    private function submitNew(string $type, array $values): FormInterface
    {
        $form = $this->forms->create($type, null, [
            'is_new' => true,
            'domains' => [$this->data->exampleCom, $this->data->exampleOrg],
            'csrf_protection' => false
        ]);
        $form->submit($values + ['domain' => 'example.com', 'enabled' => '1']);

        return $form;
    }

    /**
     * @return FormInterface<mixed>
     */
    private function submitManager(string $email): FormInterface
    {
        $form = $this->forms->create(ManagerType::class, new ManagerData(), ['csrf_protection' => false]);
        $form->submit(['email' => $email]);

        return $form;
    }

    /**
     * Returns the untranslated message templates of a field's errors.
     *
     * @param FormInterface<mixed> $form
     *
     * @return list<string>
     */
    private function errors(FormInterface $form, string $field): array
    {
        $messages = [];

        foreach ($form->get($field)->getErrors() as $error) {
            $messages[] = $error->getMessageTemplate();
        }

        return $messages;
    }
}
