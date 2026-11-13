<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\Event;

/**
 * A ballot of a poll was withdrawn.
 */
final readonly class VoteWithdrawn
{
    public function __construct(
        public string $poll,
    ) {
    }
}
