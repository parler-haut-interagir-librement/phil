<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

/**
 * What the watched words make of a text: its verdict, the text to keep — a word replaced or censored —, and the words
 * that matched.
 */
final readonly class Screening
{
    /**
     * @param list<string> $matches
     */
    public function __construct(
        public ScreeningVerdict $verdict,
        public string $text,
        public array $matches = [],
    ) {
    }

    public static function allow(string $text): self
    {
        return new self(ScreeningVerdict::Allow, $text);
    }
}
