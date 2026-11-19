<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\Messenger;

use Phil\ReactionsBundle\Application\Event\ReactionAdded;
use Phil\ReactionsBundle\Application\Event\ReactionRemoved;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sends each event on the bus too, for work that can wait: the events hold identifiers only.
 */
final readonly class EventsOnTheBus
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ReactionAdded|ReactionRemoved $event): void
    {
        $this->bus->dispatch($event);
    }
}
