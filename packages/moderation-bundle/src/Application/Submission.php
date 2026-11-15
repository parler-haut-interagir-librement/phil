<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

/**
 * What becomes of a new content: published, published and flagged, held until the moderation approves it, or refused;
 * and the text to keep, when the watched words replaced or censored a word.
 */
final readonly class Submission
{
    public function __construct(
        public ScreeningVerdict $verdict,
        public ?string $text = null,
    ) {
    }

    public function isPublished(): bool
    {
        return ScreeningVerdict::Allow === $this->verdict || ScreeningVerdict::Flag === $this->verdict;
    }
}
