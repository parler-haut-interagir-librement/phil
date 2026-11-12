<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts;

use Phil\BrokenFlowAlerts\Exception\InvalidFlowValue;
use Phil\BrokenFlowAlerts\Internal\PoissonTail;

/**
 * Whether a flow completes far less than its rhythm leads to expect.
 *
 * The history is read in two parts: the **baseline**, the days that set the rhythm, then the **window**, the last days,
 * which are compared with it. The rhythm of a window day is the mean of the baseline days of the same weekday (or of
 * every baseline day, when `$weekly` is off or the baseline is shorter than two weeks). For each stretch of the window
 * that ends on its last day — the last day, the last two days, and so on — the completions are compared with what the
 * rhythm expects, as a Poisson count: the flow is broken when so few completions would happen less often than
 * `$significance` in a working flow.
 *
 * A rare flow needs a longer stretch of silence before it is broken; a busy one is broken after a single quiet day; a
 * flow whose usual day is close to zero is never broken, since a silent day is ordinary there.
 */
final readonly class BrokenFlowRule
{
    public const int DEFAULT_BASELINE_DAYS = 28;

    public const int DEFAULT_WINDOW_DAYS = 7;

    public const float DEFAULT_SIGNIFICANCE = 0.001;

    public const int MAX_BASELINE_DAYS = 366;

    public const int MAX_WINDOW_DAYS = 31;

    private const int WEEK = 7;

    /**
     * @param int   $baselineDays the days that set the rhythm, 7 to 366
     * @param int   $windowDays   the last days compared with it, 1 to 31
     * @param float $significance above 0 and under 0.5: the lower, the fewer false alerts and the later a true one
     * @param bool  $weekly       whether each weekday has its own rhythm
     */
    public function __construct(
        public int $baselineDays = self::DEFAULT_BASELINE_DAYS,
        public int $windowDays = self::DEFAULT_WINDOW_DAYS,
        public float $significance = self::DEFAULT_SIGNIFICANCE,
        public bool $weekly = true,
    ) {
        if ($baselineDays < self::WEEK || $baselineDays > self::MAX_BASELINE_DAYS) {
            throw InvalidFlowValue::because(\sprintf('The baseline is 7 to %d days; %d was given.', self::MAX_BASELINE_DAYS, $baselineDays));
        }

        if ($windowDays < 1 || $windowDays > self::MAX_WINDOW_DAYS) {
            throw InvalidFlowValue::because(\sprintf('The window is 1 to %d days; %d was given.', self::MAX_WINDOW_DAYS, $windowDays));
        }

        if (!is_finite($significance) || $significance <= 0.0 || $significance >= 0.5) {
            throw InvalidFlowValue::because(\sprintf('The significance is above 0 and under 0.5; %s was given.', is_finite($significance) ? $significance : 'a value that is not a finite number'));
        }
    }

    /**
     * How many days of history the rule reads: the baseline, then the window.
     */
    public function historyDays(): int
    {
        return $this->baselineDays + $this->windowDays;
    }

    /**
     * @param list<int> $history one count per day, oldest first: exactly {@see self::historyDays()} of them
     */
    public function evaluate(array $history): FlowStatus
    {
        if (\count($history) !== $this->historyDays()) {
            throw InvalidFlowValue::because(\sprintf('The rule reads %d days of history; %d were given.', $this->historyDays(), \count($history)));
        }

        foreach ($history as $count) {
            if ($count < 0) {
                throw InvalidFlowValue::because('A day never has fewer than zero completions.');
            }
        }

        $baseline = \array_slice($history, 0, $this->baselineDays);
        $observed = 0;
        $expected = 0.0;
        $quietest = null;

        // From the last day backwards: each step adds one day to the stretch that ends on the last day.
        for ($days = 1; $days <= $this->windowDays; ++$days) {
            $index = $this->historyDays() - $days;
            $observed += $history[$index];
            $expected += $this->rhythmOf($baseline, $index);
            $probability = PoissonTail::atMost($observed, $expected);

            if (!$quietest instanceof FlowStatus || $probability < $quietest->probability) {
                $quietest = new FlowStatus($probability < $this->significance, $days, $observed, $expected, $probability);
            }
        }

        \assert($quietest instanceof FlowStatus);

        return $quietest;
    }

    /**
     * The completions expected on the day at this index of the history.
     *
     * @param list<int> $baseline
     */
    private function rhythmOf(array $baseline, int $index): float
    {
        $sameDays = $baseline;

        if ($this->weekly && $this->baselineDays >= 2 * self::WEEK) {
            $sameDays = [];
            // The last baseline day of the same weekday: a whole number of weeks before the day.
            $latest = $index - self::WEEK * intdiv($index - $this->baselineDays + self::WEEK, self::WEEK);

            for ($day = $latest; $day >= 0; $day -= self::WEEK) {
                $sameDays[] = $baseline[$day];
            }
        }

        return array_sum($sameDays) / \count($sameDays);
    }
}
