<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\ValueObject\AssignmentId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\TranscriptEntryId;

/**
 * One line of a learner's transcript: a course they completed, its title at that moment — the line stays readable
 * once the course is renamed or removed —, when, the credits it granted, and the assignment and cycle it fulfilled,
 * when it was assigned. Moments are kept in UTC.
 */
final class TranscriptEntry
{
    public readonly DateTimeImmutable $completedAt;

    /**
     * @param ?int<1, max> $cycle
     */
    public function __construct(
        public readonly TranscriptEntryId $id,
        public readonly LearnerId $learner,
        public readonly CourseId $course,
        public readonly string $courseTitle,
        DateTimeImmutable $completedAt,
        public readonly ?Credits $credits = null,
        public readonly ?AssignmentId $assignment = null,
        public readonly ?int $cycle = null,
    ) {
        $this->completedAt = $completedAt->setTimezone(new DateTimeZone('UTC'));
    }
}
