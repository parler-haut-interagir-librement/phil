<?php

declare(strict_types=1);

namespace Phil\CalendarSync;

use Phil\CalendarSync\Exception\InvalidCalendarData;

/**
 * Busy periods made into the fewest: cut to a window, in order, those that overlap or touch joined into one. A joined
 * period is tentative only when all it joins are; a firm one wins.
 */
final class BusyPeriods
{
    /** The most periods one read gives: a calendar that holds more within a window is refused rather than half read. */
    public const int MAX = 10_000;

    /**
     * @param iterable<BusyPeriod> $periods
     *
     * @return list<BusyPeriod>
     *
     * @throws InvalidCalendarData when more than `MAX` periods fall in the window
     */
    public static function merge(iterable $periods, SyncWindow $window): array
    {
        $kept = [];

        foreach ($periods as $period) {
            $inside = $period->within($window);

            if (!$inside instanceof BusyPeriod) {
                continue;
            }

            if (self::MAX === \count($kept)) {
                throw InvalidCalendarData::because(\sprintf('A calendar gives %d busy periods in a window at most.', self::MAX));
            }

            $kept[] = $inside;
        }

        usort($kept, static fn (BusyPeriod $one, BusyPeriod $other): int => [$one->start, $one->end] <=> [$other->start, $other->end]);
        $merged = [];
        $current = null;

        foreach ($kept as $period) {
            if (!$current instanceof BusyPeriod) {
                $current = $period;

                continue;
            }

            if ($period->start > $current->end) {
                $merged[] = $current;
                $current = $period;

                continue;
            }

            $current = new BusyPeriod($current->start, max($current->end, $period->end), $current->tentative && $period->tentative);
        }

        if ($current instanceof BusyPeriod) {
            $merged[] = $current;
        }

        return $merged;
    }
}
