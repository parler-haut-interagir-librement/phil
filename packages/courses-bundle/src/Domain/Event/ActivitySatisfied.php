<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * An activity became satisfied for a learner, by its requirement or by a waiver. Identifiers only: a listener reads the rest from the stores.
 */
final readonly class ActivitySatisfied
{
    public function __construct(
        public string $enrollment,
        public string $activity,
        public bool $waived = false,
    ) {
    }
}
