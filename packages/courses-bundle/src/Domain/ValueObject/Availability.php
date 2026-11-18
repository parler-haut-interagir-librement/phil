<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CoursesBundle\Domain\Progress\AvailabilityStatus;

/**
 * Whether a learner can work on an activity now, and what is left before they can: the moment it opens, or the
 * earlier activity to satisfy first. A learner out of tries is told a second chance can be asked for, never left
 * stuck.
 */
final readonly class Availability
{
    private function __construct(
        public AvailabilityStatus $status,
        public ?DateTimeImmutable $opensAt = null,
        public ?ActivityId $waitingFor = null,
    ) {
    }

    public static function open(): self
    {
        return new self(AvailabilityStatus::Open);
    }

    public static function satisfied(): self
    {
        return new self(AvailabilityStatus::Satisfied);
    }

    public static function scheduled(DateTimeImmutable $opensAt): self
    {
        return new self(AvailabilityStatus::Scheduled, $opensAt);
    }

    public static function waitingFor(ActivityId $activity): self
    {
        return new self(AvailabilityStatus::Locked, null, $activity);
    }

    public static function outOfTries(bool $requested): self
    {
        return new self($requested ? AvailabilityStatus::RetryRequested : AvailabilityStatus::RetryNeeded);
    }

    /**
     * Whether the learner can start, resume or report the activity now: open, or satisfied and revisited.
     */
    public function canStart(): bool
    {
        return AvailabilityStatus::Open === $this->status || AvailabilityStatus::Satisfied === $this->status;
    }
}
