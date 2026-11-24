<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Status\ContestOutcome;
use Phil\ReviewsBundle\Domain\Texts;

/**
 * The author's contest of a withdrawal, with what they bring as evidence that the review breaks none of the published
 * criteria, and how it ended once decided.
 */
final readonly class Contest
{
    public const int MAX_LENGTH = 5000;

    public string $evidence;

    /**
     * @throws InvalidReview when the evidence is empty or too long
     */
    public function __construct(
        string $evidence,
        public DateTimeImmutable $filedAt,
        public ?ContestOutcome $outcome = null,
        public ?DateTimeImmutable $settledAt = null,
    ) {
        $this->evidence = Texts::required($evidence, self::MAX_LENGTH, 'The evidence of a contest');
    }

    public function isOpen(): bool
    {
        return !$this->outcome instanceof ContestOutcome;
    }

    public function settle(ContestOutcome $outcome, DateTimeImmutable $at): self
    {
        return new self($this->evidence, $this->filedAt, $outcome, $at);
    }
}
