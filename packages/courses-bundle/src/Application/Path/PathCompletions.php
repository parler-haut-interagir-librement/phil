<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Path;

use Phil\CoursesBundle\Application\Equivalences;
use Phil\CoursesBundle\Domain\Contract\LearningPathRepository;
use Phil\CoursesBundle\Domain\Event\CourseCompleted;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * Listens to `CourseCompleted`: each open enrollment of the learner in a path that holds the course, or an
 * equivalent course, advances — the courses it opens enrolled, the path completed once its required steps are.
 */
final readonly class PathCompletions
{
    public function __construct(
        private LearningPathRepository $paths,
        private LearningPaths $learningPaths,
        private ?Equivalences $equivalences = null,
    ) {
    }

    public function __invoke(CourseCompleted $event): void
    {
        $course = CourseId::of($event->course);
        $courses = [$course, ...($this->equivalences?->equivalents($course) ?? [])];

        foreach ($this->paths->openEnrollmentsWith(LearnerId::of($event->learner), $courses) as $enrollment) {
            $this->learningPaths->advance($enrollment);
        }
    }
}
