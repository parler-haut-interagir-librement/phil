<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Outbox;

use Phil\PrivacyBundle\Application\Message\EraseModule;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Hands an `EraseModule` message that the outbox relayed to the event dispatcher over to the Messenger bus, where it is
 * retried, and where `ErasureFailureListener` sees the last failure. With the Messenger bridge of the outbox, the
 * message reaches the bus directly and this listener is never called.
 */
final readonly class EraseModuleRelay
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(EraseModule $message): void
    {
        $this->bus->dispatch($message);
    }
}
