<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A learner's progress in an activity changed: a start, a completion, a pass, a failure, a waiver or an imported result. Identifiers only: a listener reads the rest from the stores.
 */
final readonly class ActivityProgressed
{
    public function __construct(
        public string $enrollment,
        public string $activity,
    ) {
    }
}
