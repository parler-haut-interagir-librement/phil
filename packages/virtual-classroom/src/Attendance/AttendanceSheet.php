<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Attendance;

use DateTimeImmutable;

/**
 * How long a participant attended a session, from their stays: the stays are cut to the session — from its start to
 * its end, a stay still open ending with it —, the overlaps counted once, as when a participant joins from two
 * devices or when two sources report the same stay, and the seconds added up. The share is the attended seconds over
 * the seconds of the session, as a whole percentage rounded down, so that 74.9 % never passes a threshold of 75.
 */
final readonly class AttendanceSheet
{
    private function __construct()
    {
    }

    /**
     * @param list<Interval> $intervals
     *
     * @return int<0, max> the seconds attended within the session
     */
    public static function seconds(array $intervals, DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $from = $start->getTimestamp();
        $until = $end->getTimestamp();
        $spans = [];

        foreach ($intervals as $interval) {
            $joined = max($from, $interval->joinedAt->getTimestamp());
            $left = min($until, $interval->leftAt?->getTimestamp() ?? $until);

            if ($left > $joined) {
                $spans[] = [$joined, $left];
            }
        }

        usort($spans, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $total = 0;
        $reached = $from;

        foreach ($spans as [$joined, $left]) {
            $joined = max($joined, $reached);

            if ($left > $joined) {
                $total += $left - $joined;
                $reached = $left;
            }
        }

        return max(0, $total);
    }

    /**
     * @param list<Interval> $intervals
     *
     * @return int<0, 100> the share of the session attended, in whole percents rounded down; 0 for a session of no length
     */
    public static function percent(array $intervals, DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $length = $end->getTimestamp() - $start->getTimestamp();

        if ($length <= 0) {
            return 0;
        }

        return max(0, min(100, intdiv(self::seconds($intervals, $start, $end) * 100, $length)));
    }
}
