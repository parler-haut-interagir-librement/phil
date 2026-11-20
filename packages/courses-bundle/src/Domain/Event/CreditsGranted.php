<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A completion entered a learner's transcript, with the credits of its course. Identifiers only: a listener reads the
 * rest from the stores.
 */
final readonly class CreditsGranted
{
    public function __construct(
        public string $entry,
        public string $learner,
        public string $course,
    ) {
    }
}
