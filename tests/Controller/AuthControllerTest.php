<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthControllerTest extends WebTestCase
{
    public function testChangePassword(): void
    {
        $client = static::createClient();
        $client->request('GET', '/change-password');

        self::assertResponseIsSuccessful();
    }

    public function testLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
    }
}
