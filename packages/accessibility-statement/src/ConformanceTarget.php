<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

use Phil\AccessibilityCheck\ConformanceLevel;
use Phil\AccessibilityCheck\SuccessCriterion;

/**
 * What a statement claims to measure the content against: a version of WCAG and a conformance level, such as
 * WCAG 2.2 AA — the criteria of that version at that level and below.
 */
final readonly class ConformanceTarget
{
    public function __construct(
        public Standard $standard = Standard::Wcag22,
        public ConformanceLevel $level = ConformanceLevel::AA,
    ) {
    }

    /**
     * Whether the success criterion is one the target measures.
     */
    public function covers(SuccessCriterion $criterion): bool
    {
        $levels = match ($this->level) {
            ConformanceLevel::A => [ConformanceLevel::A],
            ConformanceLevel::AA => [ConformanceLevel::A, ConformanceLevel::AA],
            ConformanceLevel::AAA => [ConformanceLevel::A, ConformanceLevel::AA, ConformanceLevel::AAA],
        };

        return $this->standard->includes($criterion) && \in_array($criterion->level(), $levels, true);
    }

    /**
     * The success criteria the target measures, in the order of their numbers.
     *
     * @return list<SuccessCriterion>
     */
    public function criteria(): array
    {
        return array_values(array_filter(SuccessCriterion::cases(), $this->covers(...)));
    }

    /**
     * The target, read by a person: `WCAG 2.2 AA`.
     */
    public function describe(): string
    {
        return $this->standard->title().' '.$this->level->value;
    }
}
