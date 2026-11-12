<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts;

/**
 * What an alert says: which flow, what happened, from which day, and the numbers behind it. Days are UTC days in
 * `YYYY-MM-DD` form.
 */
final readonly class FlowAlert
{
    /**
     * @param string $day      the last whole day the alert covers
     * @param string $since    the first day of the stretch the numbers cover
     * @param int    $observed the completions counted from `$since` to `$day`
     * @param float  $expected the completions the flow's rhythm leads to expect over the same days
     */
    public function __construct(
        public Flow $flow,
        public FlowAlertKind $kind,
        public string $day,
        public string $since,
        public int $observed,
        public float $expected,
    ) {
    }
}
