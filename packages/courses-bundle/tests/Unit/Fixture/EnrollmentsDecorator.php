<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * An enrollment repository that hands every call to another: a test overrides the calls it changes.
 */
abstract class EnrollmentsDecorator implements EnrollmentRepository
{
    public function __construct(
        private readonly EnrollmentRepository $inner,
    ) {
    }

    #[Override]
    public function save(Enrollment $enrollment): void
    {
        $this->inner->save($enrollment);
    }

    #[Override]
    public function find(EnrollmentId $id): ?Enrollment
    {
        return $this->inner->find($id);
    }

    #[Override]
    public function findFor(CourseId $course, LearnerId $learner): ?Enrollment
    {
        return $this->inner->findFor($course, $learner);
    }

    #[Override]
    public function ofLearner(LearnerId $learner): array
    {
        return $this->inner->ofLearner($learner);
    }

    #[Override]
    public function progress(EnrollmentId $enrollment, ActivityId $activity): ?Progress
    {
        return $this->inner->progress($enrollment, $activity);
    }

    #[Override]
    public function progresses(EnrollmentId $enrollment): array
    {
        return $this->inner->progresses($enrollment);
    }

    #[Override]
    public function saveProgress(Progress $progress): void
    {
        $this->inner->saveProgress($progress);
    }

    #[Override]
    public function removeProgresses(EnrollmentId $enrollment): void
    {
        $this->inner->removeProgresses($enrollment);
    }

    #[Override]
    public function satisfactions(EnrollmentId $enrollment): array
    {
        return $this->inner->satisfactions($enrollment);
    }

    #[Override]
    public function claimSatisfaction(EnrollmentId $enrollment, string $subject, string $statement, DateTimeImmutable $at): bool
    {
        return $this->inner->claimSatisfaction($enrollment, $subject, $statement, $at);
    }

    #[Override]
    public function releaseSatisfaction(EnrollmentId $enrollment, string $subject): void
    {
        $this->inner->releaseSatisfaction($enrollment, $subject);
    }
}
