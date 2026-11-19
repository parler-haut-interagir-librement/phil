<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A week or a day by the hour: a column per day — per day and resource when the day is shared —, the whole days in a
 * row on top, across the days they cover when the days are not shared, and the times in their columns, side by side
 * where they overlap. A time outside the hours shown is pinned to their edge.
 *
 * Blocks are placed on the wall clock, as the hours' rows are: on the day the clocks change, 12:00 is at the row of
 * 12:00, whatever the day's length. A block shorter than half an hour — an instant — is laid out as half an hour long,
 * for two of them at one time to stand side by side.
 */
final readonly class TimeGrid
{
    /** The shortest a block is when blocks are laid out side by side, in minutes. */
    private const int SHORTEST = 30;

    /**
     * @param list<TimeColumn> $columns
     * @param list<Bar>        $allDay  across the days, when the days are not shared
     */
    private function __construct(
        public Period $period,
        public array $columns,
        public array $allDay,
        public int $firstHour,
        public int $lastHour,
        public ?Resources $resources,
    ) {
    }

    /**
     * @param list<Item> $items
     * @param int        $firstHour the first hour shown, 0 to 23
     * @param int        $lastHour  the hour the grid ends at, 1 to 24, after the first
     */
    public static function of(Period $period, array $items, ?Resources $resources = null, int $firstHour = 0, int $lastHour = 24): self
    {
        if ($firstHour < 0 || $lastHour > 24 || $firstHour >= $lastHour) {
            throw new InvalidArgumentException(\sprintf('The hours shown run from 0 to 24, the first before the last; not %d to %d.', $firstHour, $lastHour));
        }

        $days = $period->days();
        $columns = [];
        $timed = array_filter($items, static fn (Item $item): bool => !$item->allDay);
        $byResource = [];

        // Each resource's items once, not once a day: its times, and its whole days with the numbers of their days.
        if ($resources instanceof Resources) {
            foreach ($resources->columns() as $index => $resource) {
                $mine = array_filter($items, static fn (Item $item): bool => \in_array($resource, $resources->of($item), true));
                $byResource[$index] = [
                    Lanes::spans(array_filter($mine, static fn (Item $item): bool => $item->allDay)),
                    array_filter($mine, static fn (Item $item): bool => !$item->allDay),
                ];
            }
        }

        foreach ($days as $day) {
            if (!$resources instanceof Resources) {
                $columns[] = new TimeColumn($day, null, [], self::blocks($timed, $day, $firstHour, $lastHour));

                continue;
            }

            $number = Period::dayNumber($day);

            foreach ($resources->columns() as $index => $resource) {
                [$wholeDays, $times] = $byResource[$index];
                $columns[] = new TimeColumn(
                    $day,
                    $resource,
                    array_values(array_map(static fn (array $span): Item => $span[0], array_filter($wholeDays, static fn (array $span): bool => $span[1] <= $number && $span[2] >= $number))),
                    self::blocks($times, $day, $firstHour, $lastHour),
                );
            }
        }

        return new self($period, $columns, $resources instanceof Resources ? [] : Lanes::of(array_filter($items, static fn (Item $item): bool => $item->allDay), $days), $firstHour, $lastHour, $resources);
    }

    /**
     * The hours shown, for the grid's rows.
     *
     * @return list<int>
     */
    public function hours(): array
    {
        return range($this->firstHour, $this->lastHour - 1);
    }

    /**
     * @param array<int, Item> $items
     *
     * @return list<TimedBlock>
     */
    private static function blocks(array $items, DateTimeImmutable $day, int $firstHour, int $lastHour): array
    {
        $next = Period::following($day);
        $midnight = Period::dayNumber($day) * 1440;
        $opens = $firstHour * 60;
        $closes = $lastHour * 60;
        $slices = [];

        foreach ($items as $item) {
            if ($item->start < $next && ($item->end > $day || ($item->end->getTimestamp() === $item->start->getTimestamp() && $item->start >= $day))) {
                $start = min(max(self::minute($item->start, $midnight), $opens), $closes);
                $end = max(min(self::minute($item->end, $midnight), $closes), $start);
                $slices[] = [$item, $start, $end, $item->start < $day, $item->end > $next];
            }
        }

        usort($slices, static fn (array $a, array $b): int => [$a[1], $b[2], $a[0]->title] <=> [$b[1], $a[2], $b[0]->title]);
        $blocks = [];

        foreach (self::clusters($slices) as $cluster) {
            $ends = [];
            $lanes = [];

            foreach ($cluster as $index => [, $start, $end]) {
                $lane = 0;

                while (isset($ends[$lane]) && $ends[$lane] > $start) {
                    ++$lane;
                }

                $ends[$lane] = max($end, $start + self::SHORTEST);
                $lanes[$index] = $lane;
            }

            foreach ($cluster as $index => [$item, $start, $end, $before, $after]) {
                $blocks[] = new TimedBlock(
                    $item,
                    round(($start - $opens) / ($closes - $opens) * 100, 4),
                    round(($end - $start) / ($closes - $opens) * 100, 4),
                    $lanes[$index],
                    \count($ends),
                    $before,
                    $after,
                );
            }
        }

        return $blocks;
    }

    /**
     * The minute of the day a moment is at on the wall clock — its offset added to its instant —, negative before the
     * day and past 1440 after it; the day given by the minutes of its midnight since 1970-01-01.
     */
    private static function minute(DateTimeImmutable $moment, int $midnight): int
    {
        return intdiv($moment->getTimestamp() + $moment->getOffset(), 60) - $midnight;
    }

    /**
     * The slices in groups that overlap one another, each group closed when the next starts after all it holds end —
     * each at least half an hour long.
     *
     * @param list<array{Item, int, int, bool, bool}> $slices minutes of the day
     *
     * @return list<list<array{Item, int, int, bool, bool}>>
     */
    private static function clusters(array $slices): array
    {
        $clusters = [];
        $current = [];
        $reach = null;

        foreach ($slices as $slice) {
            if (null !== $reach && $slice[1] >= $reach) {
                $clusters[] = $current;
                $current = [];
                $reach = null;
            }

            $current[] = $slice;
            $reach = max($reach ?? $slice[1], $slice[2], $slice[1] + self::SHORTEST);
        }

        if ([] !== $current) {
            $clusters[] = $current;
        }

        return $clusters;
    }
}
