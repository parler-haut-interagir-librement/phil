<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\Event;

/**
 * A poll was closed by hand; a poll that reaches its closing raises no event.
 */
final readonly class PollClosed
{
    public function __construct(
        public string $poll,
    ) {
    }
}
