<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A learner completed every required step of a learning path; an application issues a badge, or awards a certificate, from it. Identifiers only: a listener reads the rest from
 * the stores.
 */
final readonly class PathCompleted
{
    public function __construct(
        public string $enrollment,
        public string $path,
        public string $learner,
    ) {
    }
}
