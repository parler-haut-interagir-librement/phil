<?php

declare(strict_types=1);

namespace Phil\TimedText;

/**
 * What a lenient reading made of a document: the track of every block it could read, and the problems of the blocks
 * it stepped over, in the order of the document.
 */
final readonly class Inspection
{
    /**
     * @param list<Issue> $issues
     */
    public function __construct(
        public Track $track,
        public array $issues = [],
    ) {
    }

    public function isValid(): bool
    {
        return [] === $this->issues;
    }
}
