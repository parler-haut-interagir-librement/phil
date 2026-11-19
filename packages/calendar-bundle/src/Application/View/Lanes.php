<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use DateTimeImmutable;

/**
 * Items laid in lanes across a row of days: each in the first lane free over its days, the earliest and the longest
 * first.
 *
 * @internal
 */
final class Lanes
{
    /**
     * @param array<int, Item>        $items
     * @param list<DateTimeImmutable> $days  the row's days, midnights in order
     *
     * @return list<Bar>
     */
    public static function of(array $items, array $days): array
    {
        return self::laid(self::spans($items), Period::dayNumber($days[0]), \count($days));
    }

    /**
     * The items with the numbers of their first and last days, counted once for all the rows they are laid in.
     *
     * @param array<int, Item> $items
     *
     * @return list<array{Item, int, int}>
     */
    public static function spans(array $items): array
    {
        $spans = [];

        foreach ($items as $item) {
            $spans[] = [$item, $item->firstDayNumber(), $item->lastDayNumber()];
        }

        return $spans;
    }

    /**
     * Items laid across a row of days, from the numbers of their days.
     *
     * @param list<array{Item, int, int}> $spans  as `spans()` gives them
     * @param int                         $first  the number of the row's first day
     * @param int                         $length how many days the row holds
     *
     * @return list<Bar>
     */
    public static function laid(array $spans, int $first, int $length): array
    {
        $last = $length - 1;
        $segments = [];

        foreach ($spans as [$item, $firstDay, $lastDay]) {
            // The columns of its days: negative before the row, past its end after it.
            $from = $firstDay - $first;
            $end = $lastDay - $first;

            if ($end >= 0 && $from <= $last) {
                $segments[] = [$item, max(0, $from), min($last, $end), $from < 0, $end > $last];
            }
        }

        // By first column, then last — the longest first —, whole days before times, then by time and title: each
        // segment's key is built once, not once a comparison.
        $keys = array_map(static fn (array $segment): array => [$segment[1], -$segment[2], !$segment[0]->allDay, $segment[0]->start->getTimestamp(), $segment[0]->start->format('u'), $segment[0]->title], $segments);
        uksort($segments, static fn (int $a, int $b): int => $keys[$a] <=> $keys[$b]);
        // The segments come by first column: a lane is free once its last segment ended before.
        $ends = [];
        $bars = [];

        foreach ($segments as [$item, $from, $through, $before, $after]) {
            $lane = 0;

            while (isset($ends[$lane]) && $ends[$lane] >= $from) {
                ++$lane;
            }

            $ends[$lane] = $through;

            $bars[] = new Bar($item, $from, $through - $from + 1, $lane, $before, $after);
        }

        return $bars;
    }
}
