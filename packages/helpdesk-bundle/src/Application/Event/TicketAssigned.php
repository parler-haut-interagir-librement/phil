<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Application\Event;

/**
 * A request assigned to an agent, or no longer assigned, by identifiers only: its conversation, the agent, or null, and
 * who assigned it.
 */
final readonly class TicketAssigned
{
    public function __construct(
        public string $conversation,
        public ?string $assignee,
        public string $by,
    ) {
    }
}
