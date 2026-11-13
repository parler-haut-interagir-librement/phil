<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\Event;

/**
 * A ballot of a poll was changed.
 */
final readonly class VoteChanged
{
    public function __construct(
        public string $poll,
    ) {
    }
}
