<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength;

/**
 * What to tell the person who typed a password: what makes it easy to guess, if anything, and how to make it harder.
 * A password scored 3 or 4 gets neither.
 */
final readonly class Feedback
{
    /**
     * @param list<Suggestion> $suggestions
     */
    public function __construct(
        public ?Warning $warning = null,
        public array $suggestions = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return !$this->warning instanceof Warning && [] === $this->suggestions;
    }
}
