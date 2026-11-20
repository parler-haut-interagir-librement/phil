<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Training;

use DateTimeImmutable;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Event\CourseCompleted;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;

/**
 * Listens to `CourseCompleted`: the learner's obligations under the course are completed, then the completion enters
 * their transcript with the assignment it fulfilled — one listener, so that the order holds.
 */
final readonly class TrainingCompletions
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private Assignments $assignments,
        private Transcripts $transcripts,
    ) {
    }

    public function __invoke(CourseCompleted $event): void
    {
        $enrollment = $this->enrollments->find(EnrollmentId::of($event->enrollment));

        if (!$enrollment instanceof Enrollment || !$enrollment->completedAt instanceof DateTimeImmutable) {
            return;
        }

        $this->transcripts->record($enrollment, $enrollment->completedAt, $this->assignments->completed($enrollment, $enrollment->completedAt));
    }
}
