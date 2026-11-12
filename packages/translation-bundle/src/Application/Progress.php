<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Application;

use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentState;

/**
 * How far the translation of a document stands: the number of segments in each state.
 */
final readonly class Progress
{
    /**
     * @param array<string, int> $counts the number of segments by state, every state present
     */
    private function __construct(
        public array $counts,
    ) {
    }

    /**
     * @param iterable<Segment> $segments
     */
    public static function of(iterable $segments): self
    {
        $counts = array_fill_keys(array_map(static fn (SegmentState $state): string => $state->value, SegmentState::cases()), 0);

        foreach ($segments as $segment) {
            ++$counts[$segment->state->value];
        }

        return new self($counts);
    }

    public function total(): int
    {
        return array_sum($this->counts);
    }

    /**
     * The segments that can be published, translated or reviewed.
     */
    public function usable(): int
    {
        return $this->counts[SegmentState::Translated->value] + $this->counts[SegmentState::Reviewed->value];
    }

    /**
     * The share of usable segments, as a whole percentage rounded down; 100 for a document without segments.
     */
    public function percent(): int
    {
        return 0 === $this->total() ? 100 : intdiv(100 * $this->usable(), $this->total());
    }
}
