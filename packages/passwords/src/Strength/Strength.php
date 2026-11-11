<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength;

/**
 * What the estimator found in a password: the guesses of the cheapest way to cut it into patterns, their order of
 * magnitude, the score, the crack times and the feedback. The sequence holds parts of the password: a strength is
 * never logged nor stored whole.
 */
final readonly class Strength
{
    /**
     * @param list<PatternMatch> $sequence the patterns that cover the analysed characters, in order
     */
    public function __construct(
        public float $guesses,
        public Score $score,
        public array $sequence,
        public CrackTimes $crackTimes,
        public Feedback $feedback,
        public int $length,
        public int $analysedLength,
    ) {
    }

    public function guessesLog10(): float
    {
        return log10($this->guesses);
    }

    /**
     * Whether only the first characters were analysed, the password being longer than the estimator's bound.
     */
    public function isTruncated(): bool
    {
        return $this->analysedLength < $this->length;
    }
}
