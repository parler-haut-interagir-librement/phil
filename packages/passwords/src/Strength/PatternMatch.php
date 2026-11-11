<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength;

/**
 * A part of a password that follows a pattern, from its character `start` to its character `end`, both included, and
 * the guesses an attacker who knows the pattern needs to find it.
 *
 * Only the fields of its pattern are filled: the dictionary and the rank of a word, the layout and the turns of a
 * keyboard pattern, the step of a sequence, the base and the count of a repeat, the day, month and year of a date. The
 * token is a part of the password: a match is never logged nor stored.
 */
final readonly class PatternMatch
{
    /**
     * @param array<array-key, string> $substitutions the characters of the token that stood for letters, each to its letter; PHP turns digit keys into integers
     */
    public function __construct(
        public Pattern $pattern,
        public int $start,
        public int $end,
        public string $token,
        public float $guesses,
        public ?string $dictionary = null,
        public int $rank = 0,
        public ?string $word = null,
        public bool $reversed = false,
        public array $substitutions = [],
        public ?string $layout = null,
        public int $turns = 0,
        public int $shifted = 0,
        public int $step = 0,
        public ?string $baseToken = null,
        public int $repeatCount = 0,
        public int $year = 0,
        public int $month = 0,
        public int $day = 0,
        public string $separator = '',
    ) {
    }

    /**
     * The number of characters of the token.
     */
    public function length(): int
    {
        return $this->end - $this->start + 1;
    }

    /**
     * Whether characters of the token stood for letters, as `@` for `a` in `p@ss`.
     */
    public function isL33t(): bool
    {
        return [] !== $this->substitutions;
    }

    public function withGuesses(float $guesses): self
    {
        return clone ($this, ['guesses' => $guesses]);
    }
}
