<?php

declare(strict_types=1);

namespace Phil\TimedText;

/**
 * A problem an inspection found and stepped over: the line of the block, when the format has lines, and what was
 * wrong with it.
 */
final readonly class Issue
{
    public function __construct(
        public ?int $line,
        public string $message,
    ) {
    }

    public function toString(): string
    {
        return null === $this->line ? $this->message : \sprintf('Line %d: %s', $this->line, $this->message);
    }
}
