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
 * report, a learning path — that keeps its own model: by the rule that raises `CourseCompleted`, every required
 * activity satisfied, the first moment kept. A completion stays once reached, even when the course gains activities
 * later. With the groups of equivalent courses, the completion of an equivalent course is recognised when the learner
 * did not complete the course itself: the earliest of them.
 */
final readonly class Completions
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private ?Equivalences $equivalences = null,
    ) {
    }

    /**
     * The learner's completion of the course, or of an equivalent course, or null when they completed none. The
     * completion names the course completed, which is an equivalent course when it differs from the one asked for.
     *
     * @phpstan-impure the answer follows the enrollments, which change between two calls
     */
    public function completion(CourseId $course, LearnerId $learner): ?Completion
    {
        $own = $this->own($course, $learner);

        if ($own instanceof Completion || !$this->equivalences instanceof Equivalences) {
            return $own;
        }

        $earliest = null;

        foreach ($this->equivalences->equivalents($course) as $equivalent) {
            $completion = $this->own($equivalent, $learner);

            if ($completion instanceof Completion && (!$earliest instanceof Completion || $completion->completedAt < $earliest->completedAt)) {
                $earliest = $completion;
            }
        }

        return $earliest;
    }

    private function own(CourseId $course, LearnerId $learner): ?Completion
    {
        $enrollment = $this->enrollments->findFor($course, $learner);

        if (!$enrollment instanceof Enrollment || !$enrollment->completedAt instanceof DateTimeImmutable) {
            return null;
        }

        return new Completion($enrollment->id, $enrollment->course, $enrollment->learner, $enrollment->completedAt);
    }
}
