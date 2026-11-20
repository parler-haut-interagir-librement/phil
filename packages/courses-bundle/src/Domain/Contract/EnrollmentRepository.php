<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * The enrollments and the progress of each in each activity.
 */
interface EnrollmentRepository
{
    /**
     * Keeps a new enrollment, or the completion of one kept already, or undone.
     */
    public function save(Enrollment $enrollment): void;

    public function find(EnrollmentId $id): ?Enrollment;

    public function findFor(CourseId $course, LearnerId $learner): ?Enrollment;

    /**
     * @return list<Enrollment> the learner's enrollments, the latest first
     */
    public function ofLearner(LearnerId $learner): array;

    public function progress(EnrollmentId $enrollment, ActivityId $activity): ?Progress;

    /**
     * @return array<string, Progress> the progress of the enrollment, by activity identifier
     */
    public function progresses(EnrollmentId $enrollment): array;

    public function saveProgress(Progress $progress): void;

    /**
     * Forgets the progress of the enrollment in every activity, for an enrollment that starts again.
     */
    public function removeProgresses(EnrollmentId $enrollment): void;

    /**
     * @return list<string> the blocks, and the course, whose `satisfied` statement the enrollment has, by identifier
     */
    public function satisfactions(EnrollmentId $enrollment): array;

    /**
     * Notes that the `satisfied` statement of a block or of the course is issued for the enrollment, once: says whether
     * this call noted it, false when it was noted already.
     *
     * @param string $subject the identifier of the block, or of the course
     */
    public function claimSatisfaction(EnrollmentId $enrollment, string $subject, string $statement, DateTimeImmutable $at): bool;

    /**
     * Forgets a satisfaction noted by `claimSatisfaction()` whose statement could not be kept.
     */
    public function releaseSatisfaction(EnrollmentId $enrollment, string $subject): void;
}
