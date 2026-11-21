<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\SubmissionCredentials;
use App\Tests\TestData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SubmissionCredentialsTest extends TestCase
{
    private Session $session;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);
        $this->requestStack = new RequestStack([$request]);
    }

    public function testPasswordIsKeptEncrypted(): void
    {
        $data = new TestData();
        $credentials = new SubmissionCredentials($this->requestStack, 'short');
        $credentials->store($data->alice, 'correct horse battery staple');

        self::assertSame('correct horse battery staple', $credentials->get($data->alice));
        self::assertStringNotContainsString('horse', serialize($this->session->all()));
    }

    public function testPasswordIsBoundToUser(): void
    {
        $data = new TestData();
        $credentials = new SubmissionCredentials($this->requestStack, 'secret');
        $credentials->store($data->alice, 'correct horse battery staple');

        self::assertNull($credentials->get($data->bob));
    }

    public function testPasswordIsBoundToSecret(): void
    {
        $data = new TestData();
        (new SubmissionCredentials($this->requestStack, 'secret'))->store($data->alice, 'correct horse battery staple');

        self::assertNull((new SubmissionCredentials($this->requestStack, 'other secret'))->get($data->alice));
    }

    public function testClear(): void
    {
        $data = new TestData();
        $credentials = new SubmissionCredentials($this->requestStack, 'secret');
        $credentials->store($data->alice, 'correct horse battery staple');
        $credentials->clear();

        self::assertNull($credentials->get($data->alice));
    }

    public function testWithoutSession(): void
    {
        $data = new TestData();
        $credentials = new SubmissionCredentials(new RequestStack(), 'secret');
        $credentials->store($data->alice, 'correct horse battery staple');

        self::assertNull($credentials->get($data->alice));
    }
}
