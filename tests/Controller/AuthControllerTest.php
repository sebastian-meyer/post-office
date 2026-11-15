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

    public function testPagesCannotBeFramed(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseHeaderSame('Content-Security-Policy', "frame-ancestors 'none'");
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }

    public function testLogoutWithForm(): void
    {
        $this->client->loginUser($this->data->alice);
        $crawler = $this->client->request('GET', '/account');
        // The logout form is hidden and submitted by a button elsewhere in the navigation.
        $form = $crawler->filter('form#logout')->form();
        $this->client->submit($form, [], ['HTTP_ORIGIN' => 'http://localhost']);

        $this->client->request('GET', '/account');
        self::assertResponseRedirects('/login');
    }

    public function testLogoutRequiresSameOrigin(): void
    {
        $this->client->loginUser($this->data->alice);

        // A link or form on another site must not log the user out.
        $this->client->request('GET', '/logout');
        // (The headers are what browsers send for a cross-site form; the test client would add a same-origin Referer.)
        $this->client->request('POST', '/logout', ['_csrf_token' => 'csrf-token'], [], [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_REFERER' => 'https://evil.example/',
            'HTTP_SEC_FETCH_SITE' => 'cross-site'
        ]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();
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
