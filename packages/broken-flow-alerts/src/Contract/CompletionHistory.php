<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts\Contract;

use DateTimeImmutable;
use Phil\BrokenFlowAlerts\Flow;

/**
 * How many times a flow was completed on each UTC day: the application's counters, read and never written.
 */
interface CompletionHistory
{
    /**
     * One count per UTC day, oldest first, starting on the day of `$firstDay` (midnight UTC): exactly `$days` whole
     * numbers from zero. A day without a completion is a zero, not a gap.
     *
     * @param int $days at least 1
     *
     * @return list<int>
     */
    public function dailyCompletions(Flow $flow, DateTimeImmutable $firstDay, int $days): array;
}
