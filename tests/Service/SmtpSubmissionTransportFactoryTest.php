<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\SmtpSubmissionTransportFactory;
use App\Tests\TestData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport;

final class SmtpSubmissionTransportFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedDsns(): iterable
    {
        yield 'null transport' => ['null://null'];
        yield 'sendmail' => ['sendmail://default'];
    }

    #[DataProvider('unsupportedDsns')]
    public function testOnlySmtpIsAccepted(string $dsn): void
    {
        $factory = new SmtpSubmissionTransportFactory(new Transport(Transport::getDefaultFactories()), $dsn);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('MAILER_DSN must point to the submission port');
        $factory->create((new TestData())->alice, 'secret');
    }
}
