<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\Event;

/**
 * A ballot was cast in a poll; the event names the poll only, whether the poll is anonymous or not.
 */
final readonly class VoteCast
{
    public function __construct(
        public string $poll,
    ) {
    }
}
