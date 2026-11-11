<?php

declare(strict_types=1);

namespace Phil\Reservation\Availability;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Reservation\Contract\Availability;
use Phil\Reservation\Exception\InvalidDefinition;
use Phil\Reservation\Exception\InvalidInterval;
use Phil\Reservation\Interval;

/**
 * Hours that repeat each week on the resource's clock, with days closed: Monday to Friday from 9:00 to 17:00, the
 * evening on Saturday. A range ending at midnight touches the next day's range from midnight: the two make one span,
 * so a booking can cross midnight. A span is read from the day before the period to the day after it: open around
 * the clock, it is given from that first midnight to that last one. On a day the clock changes, a time that does not exist is read as the time after
 * the change.
 */
final readonly class WeeklyAvailability implements Availability
{
    /** The longest period read at once, in days. */
    public const int MAX_DAYS = 1000;

    /** @var array<int, list<array{int, int}>> the ranges of each ISO weekday, in minutes from midnight, in order */
    public array $hours;

    /** @var array<string, string> the days closed, as `Y-m-d`, by themselves */
    private array $closed;

    /**
     * @param array<int, list<array{int, int}>> $hours  by ISO weekday, 1 for Monday to 7 for Sunday: ranges from a minute
     *                                                  of the day to a later one, 1440 for midnight at its end
     * @param list<string>                      $closed days closed whatever their weekday, as `Y-m-d`
     *
     * @throws InvalidDefinition when a weekday, a range or a day is not one
     */
    public function __construct(
        array $hours,
        public DateTimeZone $timezone,
        array $closed = [],
    ) {
        $ordered = [];

        foreach ($hours as $weekday => $ranges) {
            if ($weekday < 1 || $weekday > 7) {
                throw InvalidDefinition::because(\sprintf('A weekday is numbered from 1 for Monday to 7 for Sunday, not %d.', $weekday));
            }

            $ordered[$weekday] = $this->ordered($ranges);
        }

        ksort($ordered);
        $this->hours = $ordered;
        $days = [];

        foreach ($closed as $day) {
            $read = DateTimeImmutable::createFromFormat('!Y-m-d', $day, $timezone);

            if (false === $read || $read->format('Y-m-d') !== $day) {
                throw InvalidDefinition::because(\sprintf('A closed day is written as Y-m-d, not "%s".', mb_substr($day, 0, 40)));
            }

            $days[$day] = $day;
        }

        $this->closed = $days;
    }

    #[Override]
    public function windows(Interval $period): array
    {
        $first = $period->start->setTimezone($this->timezone)->setTime(0, 0);
        $last = $period->end->setTimezone($this->timezone);

        if ($first->diff($last)->days > self::MAX_DAYS) {
            throw InvalidInterval::because(\sprintf('Weekly hours are read over %d days at most.', self::MAX_DAYS));
        }

        $windows = [];
        $open = null;

        // From the day before to the day after: a span that meets the period may start the day before its first day and
        // end the day after its last one.
        for ($day = $first->modify('-1 day'), $after = $last->modify('+1 day'); $day <= $after; $day = $day->modify('+1 day')) {
            if (isset($this->closed[$day->format('Y-m-d')])) {
                continue;
            }

            foreach ($this->hours[(int) $day->format('N')] ?? [] as [$from, $until]) {
                $start = $day->setTime(intdiv($from, 60), $from % 60)->getTimestamp();
                $end = $day->setTime(intdiv($until, 60), $until % 60)->getTimestamp();

                if (null !== $open && $start <= $open[1]) {
                    $open[1] = $end;

                    continue;
                }

                if (null !== $open) {
                    $windows[] = $open;
                }

                $open = [$start, $end];
            }
        }

        if (null !== $open) {
            $windows[] = $open;
        }

        $kept = [];

        foreach ($windows as [$start, $end]) {
            if ($start < $period->until && $period->from < $end) {
                $kept[] = Interval::ofTimestamps($start, $end, $this->timezone);
            }
        }

        return $kept;
    }

    /**
     * @param list<array{int, int}> $ranges
     *
     * @return list<array{int, int}>
     */
    private function ordered(array $ranges): array
    {
        $byStart = [];

        foreach ($ranges as $range) {
            if (isset($byStart[$range[0]])) {
                throw InvalidDefinition::because('The ranges of hours of a day do not overlap.');
            }

            $byStart[$range[0]] = $range;
        }

        ksort($byStart);
        $ranges = array_values($byStart);
        $previous = null;

        foreach ($ranges as [$from, $until]) {
            if ($from < 0 || $until > 1440 || $until <= $from) {
                throw InvalidDefinition::because(\sprintf('A range of hours goes from a minute of the day to a later one, up to 1440: not %d to %d.', $from, $until));
            }

            if (null !== $previous && $from < $previous) {
                throw InvalidDefinition::because('The ranges of hours of a day do not overlap.');
            }

            $previous = $until;
        }

        return $ranges;
    }
}
