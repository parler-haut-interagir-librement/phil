<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A session of a SCORM activity ended — terminated by the content, or by the learner leaving the page —: the
 * enrollment, the activity and the attempt, by their identifiers, and whether the attempt ended with it.
 */
final readonly class ScormSessionEnded
{
    public function __construct(
        public string $enrollment,
        public string $activity,
        public string $attempt,
        public bool $attemptEnded,
    ) {
    }
}
