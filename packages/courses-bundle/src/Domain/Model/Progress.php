<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\Score;

/**
 * A learner's progress in one activity: started, completed, passed or failed, waived, the best score, the failed
 * tries and the second chances granted, and the moment the activity was satisfied. A completion or a pass is never
 * undone by a later failure: the learner keeps what they reached. Moments are kept in UTC.
 */
final class Progress
{
    public private(set) ?DateTimeImmutable $startedAt;

    public private(set) ?DateTimeImmutable $satisfiedAt;

    public private(set) DateTimeImmutable $updatedAt;

    public function __construct(
        public readonly EnrollmentId $enrollment,
        public readonly ActivityId $activity,
        DateTimeImmutable $updatedAt,
        public private(set) bool $completed = false,
        public private(set) ?bool $success = null,
        public private(set) bool $waived = false,
        public private(set) ?Score $score = null,
        public private(set) int $failures = 0,
        public private(set) int $extraTries = 0,
        ?DateTimeImmutable $startedAt = null,
        ?DateTimeImmutable $satisfiedAt = null,
    ) {
        $this->updatedAt = $updatedAt->setTimezone(new DateTimeZone('UTC'));
        $this->startedAt = $startedAt?->setTimezone(new DateTimeZone('UTC'));
        $this->satisfiedAt = $satisfiedAt?->setTimezone(new DateTimeZone('UTC'));
    }

    public function start(DateTimeImmutable $at): void
    {
        $this->startedAt ??= $at->setTimezone(new DateTimeZone('UTC'));
        $this->updatedAt = $at->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Applies an outcome and says whether the activity became satisfied by it.
     */
    public function apply(Outcome $outcome, CompletionRequirement $requirement, DateTimeImmutable $at): bool
    {
        $this->start($at);
        $this->completed = $this->completed || $outcome->completed;

        if (true === $outcome->success) {
            $this->success = true;
        } elseif (false === $outcome->success) {
            ++$this->failures;
            $this->success ??= false;
        }

        if ($outcome->score instanceof Score && (!$this->score instanceof Score || $outcome->score->scaled > $this->score->scaled)) {
            $this->score = $outcome->score;
        }

        return $this->satisfy($requirement, $at);
    }

    /**
     * Waives the activity: the course counts it satisfied without the learner doing it.
     */
    public function waive(DateTimeImmutable $at): bool
    {
        $this->waived = true;
        $this->updatedAt = $at->setTimezone(new DateTimeZone('UTC'));

        return $this->markSatisfied($at);
    }

    /**
     * Grants one more try to a learner out of them.
     */
    public function grantTry(DateTimeImmutable $at): void
    {
        ++$this->extraTries;
        $this->updatedAt = $at->setTimezone(new DateTimeZone('UTC'));
    }

    public function isSatisfied(): bool
    {
        return $this->satisfiedAt instanceof DateTimeImmutable;
    }

    /**
     * Whether every try was failed: the learner can only go on with a second chance or a waiver.
     */
    public function isOutOfTries(?int $maxAttempts): bool
    {
        return null !== $maxAttempts && !$this->isSatisfied() && true !== $this->success && $this->failures >= $maxAttempts + $this->extraTries;
    }

    /**
     * Notes the activity satisfied when its requirement is met; says whether it became so now.
     */
    public function satisfy(CompletionRequirement $requirement, DateTimeImmutable $at): bool
    {
        return $requirement->isMetBy($this->completed, $this->success) && $this->markSatisfied($at);
    }

    private function markSatisfied(DateTimeImmutable $at): bool
    {
        if ($this->satisfiedAt instanceof DateTimeImmutable) {
            return false;
        }

        $this->satisfiedAt = $at->setTimezone(new DateTimeZone('UTC'));

        return true;
    }
}
