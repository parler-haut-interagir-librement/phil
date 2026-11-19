<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Application\Event;

/**
 * A request opened, by identifiers only: its conversation, its queue and its requester.
 */
final readonly class TicketOpened
{
    public function __construct(
        public string $conversation,
        public string $queue,
        public string $requester,
    ) {
    }
}
