<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\ValueObject;

/**
 * What a learner did in an activity, as a statement, a manual completion or an imported result reports it: completed
 * or not, passed, failed or not judged, and the score when there is one.
 */
final readonly class Outcome
{
    public function __construct(
        public bool $completed = false,
        public ?bool $success = null,
        public ?Score $score = null,
    ) {
    }

    public static function completed(): self
    {
        return new self(true);
    }

    public static function passed(?Score $score = null): self
    {
        return new self(false, true, $score);
    }

    public static function failed(?Score $score = null): self
    {
        return new self(false, false, $score);
    }

    public function isEmpty(): bool
    {
        return !$this->completed && null === $this->success;
    }
}
