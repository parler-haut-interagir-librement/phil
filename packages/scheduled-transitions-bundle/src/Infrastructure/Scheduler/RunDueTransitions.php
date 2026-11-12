<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Infrastructure\Scheduler;

/**
 * The message Symfony Scheduler sends on each tick: apply the transitions that are due.
 */
final readonly class RunDueTransitions
{
    public function __construct(
        public int $limit = 100,
    ) {
    }
}
