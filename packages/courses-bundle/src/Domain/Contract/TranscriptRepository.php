<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Contract;

use Phil\CoursesBundle\Domain\Model\TranscriptEntry;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * The transcripts of the learners, and the credits each course grants.
 */
interface TranscriptRepository
{
    public function save(TranscriptEntry $entry): void;

    /**
     * @return list<TranscriptEntry> the learner's transcript, the latest completion first
     */
    public function of(LearnerId $learner): array;

    public function removeOf(LearnerId $learner): void;

    /**
     * Sets the credits the course grants, or none.
     */
    public function setCredits(CourseId $course, ?Credits $credits): void;

    public function credits(CourseId $course): ?Credits;
}
