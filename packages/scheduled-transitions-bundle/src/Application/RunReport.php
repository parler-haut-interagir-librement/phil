<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Application;

/**
 * What a run did: the transitions applied, and those that failed this time.
 */
final readonly class RunReport
{
    public function __construct(
        public int $applied = 0,
        public int $failed = 0,
    ) {
    }
}
