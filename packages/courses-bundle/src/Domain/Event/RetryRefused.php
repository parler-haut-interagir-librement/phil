<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A second chance was refused. Identifiers only: a listener reads the rest from the stores.
 */
final readonly class RetryRefused
{
    public function __construct(
        public string $request,
        public string $enrollment,
        public string $activity,
        public string $decidedBy,
    ) {
    }
}
