<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts;

/**
 * The verdict of {@see BrokenFlowRule} on one history: whether the flow is broken, and over which of the last days.
 */
final readonly class FlowStatus
{
    /**
     * @param int   $days        how many of the last days the numbers cover, from 1 to the rule's window
     * @param int   $observed    the completions over those days
     * @param float $expected    the completions the rhythm leads to expect over those days
     * @param float $probability how likely so few completions are when the flow works
     */
    public function __construct(
        public bool $broken,
        public int $days,
        public int $observed,
        public float $expected,
        public float $probability,
    ) {
    }
}
