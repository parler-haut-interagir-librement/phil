<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use DateTimeImmutable;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * A learner's completion of a course: the enrollment that completed it, the course completed — an equivalent course
 * when `Completions` recognised it for another —, and the moment, in UTC, when its last required activity was
 * satisfied — the moment `CourseCompleted` was raised.
 */
final readonly class Completion
{
    public function __construct(
        public EnrollmentId $enrollment,
        public CourseId $course,
        public LearnerId $learner,
        public DateTimeImmutable $completedAt,
    ) {
    }
}
