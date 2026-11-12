<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Memory;

/**
 * A unit of the memory proposed for a source, with its score from 0 to 1 — 1 for an exact match only — and its kind.
 */
final readonly class MemoryMatch
{
    public function __construct(
        public MemoryUnit $unit,
        public float $score,
        public MatchKind $kind,
    ) {
    }

    /**
     * The score as a whole percentage, rounded down, as translation tools show it.
     */
    public function percent(): int
    {
        return (int) floor($this->score * 100 + Similarity::EPSILON);
    }
}
