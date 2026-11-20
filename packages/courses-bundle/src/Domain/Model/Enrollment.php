<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Symfony\Component\Uid\Uuid;

/**
 * A learner's enrollment in a course: since when, when they completed it, and the registration that ties the cmi5
 * statements of this enrollment together — a UUID, the same for every launch. Moments are kept in UTC.
 */
final class Enrollment
{
    public readonly DateTimeImmutable $enrolledAt;

    public readonly string $registration;

    public private(set) ?DateTimeImmutable $completedAt;

    public function __construct(
        public readonly EnrollmentId $id,
        public readonly CourseId $course,
        public readonly LearnerId $learner,
        DateTimeImmutable $enrolledAt,
        ?string $registration = null,
        ?DateTimeImmutable $completedAt = null,
    ) {
        $this->enrolledAt = $enrolledAt->setTimezone(new DateTimeZone('UTC'));
        $this->registration = $registration ?? Uuid::v4()->toRfc4122();
        $this->completedAt = $completedAt?->setTimezone(new DateTimeZone('UTC'));
    }

    public function isCompleted(): bool
    {
        return $this->completedAt instanceof DateTimeImmutable;
    }

    /**
     * Notes that the course is completed; a later call keeps the first moment.
     */
    public function complete(DateTimeImmutable $at): bool
    {
        if ($this->completedAt instanceof DateTimeImmutable) {
            return false;
        }

        $this->completedAt = $at->setTimezone(new DateTimeZone('UTC'));

        return true;
    }

    /**
     * Undoes the completion, for an enrollment that starts again: says whether it was completed.
     */
    public function reopen(): bool
    {
        if (!$this->completedAt instanceof DateTimeImmutable) {
            return false;
        }

        $this->completedAt = null;

        return true;
    }
}
