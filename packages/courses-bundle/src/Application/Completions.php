<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use DateTimeImmutable;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * Answers whether a learner completed a course, for another part of the application — certificates, badges, a
 * report — that keeps its own model: by the rule that raises `CourseCompleted`, every required activity satisfied, the
 * first moment kept. A completion stays once reached, even when the course gains activities later.
 */
final readonly class Completions
{
    public function __construct(
        private EnrollmentRepository $enrollments,
    ) {
    }

    /**
     * The learner's completion of the course, or null when they are not enrolled or have not completed it.
     *
     * @phpstan-impure the answer follows the enrollments, which change between two calls
     */
    public function completion(CourseId $course, LearnerId $learner): ?Completion
    {
        $enrollment = $this->enrollments->findFor($course, $learner);

        if (!$enrollment instanceof Enrollment || !$enrollment->completedAt instanceof DateTimeImmutable) {
            return null;
        }

        return new Completion($enrollment->id, $enrollment->course, $enrollment->learner, $enrollment->completedAt);
    }
}
