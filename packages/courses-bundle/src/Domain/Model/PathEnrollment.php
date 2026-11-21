<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\LearningPathId;
use Phil\CoursesBundle\Domain\ValueObject\PathEnrollmentId;

/**
 * A learner's enrollment in a learning path: since when, and when they completed every required step. Moments are
 * kept in UTC.
 */
final class PathEnrollment
{
    public readonly DateTimeImmutable $enrolledAt;

    public private(set) ?DateTimeImmutable $completedAt;

    public function __construct(
        public readonly PathEnrollmentId $id,
        public readonly LearningPathId $path,
        public readonly LearnerId $learner,
        DateTimeImmutable $enrolledAt,
        ?DateTimeImmutable $completedAt = null,
    ) {
        $this->enrolledAt = $enrolledAt->setTimezone(new DateTimeZone('UTC'));
        $this->completedAt = $completedAt?->setTimezone(new DateTimeZone('UTC'));
    }

    public function isCompleted(): bool
    {
        return $this->completedAt instanceof DateTimeImmutable;
    }

    /**
     * Notes that the path is completed; a later call keeps the first moment.
     */
    public function complete(DateTimeImmutable $at): bool
    {
        if ($this->completedAt instanceof DateTimeImmutable) {
            return false;
        }

        $this->completedAt = $at->setTimezone(new DateTimeZone('UTC'));

        return true;
    }
}
