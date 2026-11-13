<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\Event;

/**
 * A poll was deleted, with its ballots.
 */
final readonly class PollDeleted
{
    public function __construct(
        public string $poll,
    ) {
    }
}
