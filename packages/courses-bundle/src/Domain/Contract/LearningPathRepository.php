<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Contract;

use Phil\CoursesBundle\Domain\Model\LearningPath;
use Phil\CoursesBundle\Domain\Model\PathEnrollment;
use Phil\CoursesBundle\Domain\Model\PathStep;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\LearningPathId;
use Phil\CoursesBundle\Domain\ValueObject\PathEnrollmentId;

/**
 * The learning paths, their steps and the learners enrolled in them.
 */
interface LearningPathRepository
{
    public function save(LearningPath $path): void;

    public function find(LearningPathId $id): ?LearningPath;

    /**
     * @return list<LearningPath> a page of the paths, the latest first
     */
    public function all(int $limit = 50, int $offset = 0): array;

    /**
     * Removes the path with its steps and its enrollments; the enrollments in its courses stay.
     */
    public function remove(LearningPathId $id): void;

    /**
     * Replaces the steps of the path with these.
     *
     * @param list<PathStep> $steps
     */
    public function saveSteps(LearningPathId $path, array $steps): void;

    /**
     * @return list<PathStep> the steps of the path, in their order
     */
    public function steps(LearningPathId $path): array;

    public function saveEnrollment(PathEnrollment $enrollment): void;

    public function enrollment(PathEnrollmentId $id): ?PathEnrollment;

    public function enrollmentFor(LearningPathId $path, LearnerId $learner): ?PathEnrollment;

    /**
     * @return list<PathEnrollment> the learner's enrollments in paths, the latest first
     */
    public function enrollmentsOf(LearnerId $learner): array;

    /**
     * @param list<CourseId> $courses
     *
     * @return list<PathEnrollment> the learner's enrollments not completed in the paths that hold one of these courses
     */
    public function openEnrollmentsWith(LearnerId $learner, array $courses): array;

    /**
     * Removes the learner's enrollments in paths.
     */
    public function removeEnrollmentsOf(LearnerId $learner): void;
}
