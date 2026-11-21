<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Service\SubmissionTransportFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Discards all emails instead of connecting to a mail server (used in tests, see config/services.yaml).
 *
 * The emails can still be inspected with the mailer assertions, since the transport dispatches the mailer events.
 */
final class NullSubmissionTransportFactory implements SubmissionTransportFactory
{
    public function create(Account $sender, #[\SensitiveParameter] string $password): TransportInterface
    {
        return $this->transportFactory->fromString('null://null');
    }

    public function __construct(
        #[Autowire(service: 'mailer.transport_factory')]
        private readonly Transport $transportFactory
    ) {
    }
}
