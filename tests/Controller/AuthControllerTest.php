<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\DatabaseTrait;
use App\Tests\TestData;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthControllerTest extends WebTestCase
{
    use DatabaseTrait;

    private KernelBrowser $client;

    private TestData $data;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Keep the kernel (and thus the database transaction) between requests.
        $this->client->disableReboot();
        $this->data = (new TestData())->persist($this->startDatabase());
    }

    public function testLoginPageIsPublic(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
    }

    public function testChangePasswordRequiresLogin(): void
    {
        $this->client->request('GET', '/change-password');

        self::assertResponseRedirects('/login');
    }

    public function testChangePasswordPageForLoggedInUser(): void
    {
        $this->client->loginUser($this->data->alice);
        $this->client->request('GET', '/change-password');

        self::assertResponseIsSuccessful();
    }

    public function testAccountPageForLoggedInUser(): void
    {
        $this->client->loginUser($this->data->alice);
        $this->client->request('GET', '/account');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('dd', 'alice@example.com');
    }

    public function testDisabledUserIsLoggedOut(): void
    {
        $this->client->loginUser($this->data->alice);
        $this->data->alice->disable();
        $this->entityManager->flush();

        $this->client->request('GET', '/account');

        self::assertResponseRedirects('/login');
    }
}
