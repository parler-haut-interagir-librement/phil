<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\Messenger;

use Phil\SocialCardsBundle\Application\Contract\CardQueue;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues the cards on the message bus, as `GenerateSocialCard` messages, routed to a transport of the application's
 * choice.
 */
final readonly class MessengerCardQueue implements CardQueue
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function queue(string $fingerprint): void
    {
        $this->bus->dispatch(new GenerateSocialCard($fingerprint));
    }
}
