<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\Event;

/**
 * A poll was created.
 */
final readonly class PollCreated
{
    public function __construct(
        public string $poll,
    ) {
    }
}
