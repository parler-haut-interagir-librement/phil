<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Messenger;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Relays the events of the outbox to a Messenger bus instead of the PSR-14 listeners: a record is delivered once the
 * bus accepted its event, handled by a synchronous handler or sent to its transport. A handler that fails, or a
 * transport that refuses the message, leaves the record to be tried again.
 */
final readonly class MessengerDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    #[Override]
    public function dispatch(object $event): object
    {
        $this->bus->dispatch($event);

        return $event;
    }
}
