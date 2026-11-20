<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Training;

/**
 * What a run of the mandatory training did: the members of groups and units it reached, the reminders it sent, the
 * completions it renewed, and the assignments it skipped, whose members could not be named.
 */
final readonly class TrainingReport
{
    public function __construct(
        public int $reached = 0,
        public int $reminded = 0,
        public int $renewed = 0,
        public int $skipped = 0,
    ) {
    }
}
