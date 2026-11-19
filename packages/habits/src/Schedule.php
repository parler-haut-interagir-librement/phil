<?php

declare(strict_types=1);

namespace Phil\Habits;

use Generator;
use Phil\Habits\Exception\InvalidSchedule;

/**
 * When a habit is due: every day, once in every N days, on some days of the week, or N times a week or a month.
 *
 * A schedule cuts the habit's days into periods, each asking a number of check-ins. It is written as a short rule,
 * which `parse()` reads and `toString()` writes back:
 *
 * | Rule | Periods |
 * |---|---|
 * | `daily` | Each day, one check-in. |
 * | `every 3 days` | Each window of 3 days from the habit's first day, one check-in; 2 to 365 days. |
 * | `on mon,wed,fri` | Each of these days, one check-in; the other days ask nothing. |
 * | `3 per week` | Each ISO week, Monday to Sunday, 3 check-ins; 1 to 7. |
 * | `10 per month` | Each calendar month, 10 check-ins; 1 to 31. |
 */
final readonly class Schedule
{
    /** The longest rule `parse()` reads, in bytes. */
    public const int MAX_RULE_LENGTH = 64;

    private const array WEEKDAYS = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];

    /**
     * @param list<int<1, 7>> $weekdays
     */
    private function __construct(
        public Frequency $frequency,
        public int $count,
        public array $weekdays,
    ) {
    }

    public static function daily(): self
    {
        return new self(Frequency::Daily, 1, []);
    }

    /**
     * @throws InvalidSchedule
     */
    public static function everyDays(int $days): self
    {
        if ($days < 2 || $days > 365) {
            throw InvalidSchedule::because('A habit due once in every N days takes 2 to 365 days.');
        }

        return new self(Frequency::EveryDays, $days, []);
    }

    /**
     * @param list<int> $weekdays ISO 8601 numbers, 1 for Monday to 7 for Sunday, in any order
     *
     * @throws InvalidSchedule
     */
    public static function onWeekdays(array $weekdays): self
    {
        $days = [];

        foreach ($weekdays as $weekday) {
            if ($weekday < 1 || $weekday > 7) {
                throw InvalidSchedule::because('A day of the week is numbered from 1 for Monday to 7 for Sunday.');
            }

            $days[$weekday] = $weekday;
        }

        if ([] === $days) {
            throw InvalidSchedule::because('A habit due on some days of the week names at least one.');
        }

        ksort($days);

        return new self(Frequency::Weekdays, \count($days), array_values($days));
    }

    /**
     * @throws InvalidSchedule
     */
    public static function perWeek(int $times): self
    {
        if ($times < 1 || $times > 7) {
            throw InvalidSchedule::because('A habit due N times a week takes 1 to 7 times.');
        }

        return new self(Frequency::PerWeek, $times, []);
    }

    /**
     * @throws InvalidSchedule
     */
    public static function perMonth(int $times): self
    {
        if ($times < 1 || $times > 31) {
            throw InvalidSchedule::because('A habit due N times a month takes 1 to 31 times.');
        }

        return new self(Frequency::PerMonth, $times, []);
    }

    /**
     * Reads a rule, in any case, words separated by spaces.
     *
     * @throws InvalidSchedule
     */
    public static function parse(string $rule): self
    {
        if (\strlen($rule) > self::MAX_RULE_LENGTH) {
            throw InvalidSchedule::because(\sprintf('A schedule rule is at most %d bytes long.', self::MAX_RULE_LENGTH));
        }

        $words = preg_split('/[ \t]+/', strtolower($rule), -1, \PREG_SPLIT_NO_EMPTY);
        \assert(\is_array($words));

        return match (true) {
            ['daily'] === $words => self::daily(),
            3 === \count($words) && 'every' === $words[0] && 'days' === $words[2] => self::everyDays(self::number($words[1])),
            3 === \count($words) && 'per' === $words[1] && 'week' === $words[2] => self::perWeek(self::number($words[0])),
            3 === \count($words) && 'per' === $words[1] && 'month' === $words[2] => self::perMonth(self::number($words[0])),
            2 <= \count($words) && 'on' === $words[0] => self::onWeekdays(self::weekdays(\array_slice($words, 1))),
            default => throw InvalidSchedule::because('A schedule rule is "daily", "every N days", "on mon,wed", "N per week" or "N per month".'),
        };
    }

    /**
     * The rule of the schedule, as `parse()` reads it.
     */
    public function toString(): string
    {
        return match ($this->frequency) {
            Frequency::Daily => 'daily',
            Frequency::EveryDays => \sprintf('every %d days', $this->count),
            Frequency::Weekdays => 'on '.implode(',', array_map(static fn (int $day): string => array_flip(self::WEEKDAYS)[$day], $this->weekdays)),
            Frequency::PerWeek => \sprintf('%d per week', $this->count),
            Frequency::PerMonth => \sprintf('%d per month', $this->count),
        };
    }

    /**
     * What a streak counts under this schedule.
     */
    public function streakUnit(): StreakUnit
    {
        return match ($this->frequency) {
            Frequency::Daily, Frequency::Weekdays => StreakUnit::Day,
            Frequency::EveryDays => StreakUnit::Interval,
            Frequency::PerWeek => StreakUnit::Week,
            Frequency::PerMonth => StreakUnit::Month,
        };
    }

    /**
     * The periods from a habit's first day to a day, in order, the last one holding that day; nothing when the day is
     * before the first one. A period cut by the first day asks no more check-ins than it has days.
     *
     * @return Generator<int, Period>
     */
    public function periods(Day $first, Day $through): Generator
    {
        $day = $first;

        while (!$day->isAfter($through)) {
            if (Frequency::Weekdays === $this->frequency && !\in_array($day->weekday(), $this->weekdays, true)) {
                $day = $day->plus(1);

                continue;
            }

            [$start, $end, $span] = $this->window($first, $day);
            $length = $start->daysUntil($end) + 1;

            yield new Period($start, $end, min($this->required(), $length), $span);

            $day = $end->plus(1);
        }
    }

    /**
     * The period of a day, as the habit that starts on a first day cuts it, with the number of days it stands for.
     *
     * @return array{Day, Day, float}
     */
    private function window(Day $first, Day $day): array
    {
        return match ($this->frequency) {
            Frequency::Daily => [$day, $day, 1.0],
            Frequency::Weekdays => [$day, $day, 7 / $this->count],
            Frequency::EveryDays => [$day, $day->plus($this->count - 1), $this->count],
            Frequency::PerWeek => $this->clipped($first, $day->weekStart(), $day->weekStart()->plus(6)),
            Frequency::PerMonth => $this->clipped($first, $day->monthStart(), $day->monthEnd()),
        };
    }

    private function required(): int
    {
        return match ($this->frequency) {
            Frequency::PerWeek, Frequency::PerMonth => $this->count,
            default => 1,
        };
    }

    /**
     * @return array{Day, Day, float}
     */
    private function clipped(Day $first, Day $start, Day $end): array
    {
        $start = $start->isBefore($first) ? $first : $start;

        return [$start, $end, $start->daysUntil($end) + 1];
    }

    /**
     * @throws InvalidSchedule
     */
    private static function number(string $word): int
    {
        if (1 !== preg_match('/^[1-9]\d{0,2}$/D', $word)) {
            throw InvalidSchedule::because('A count in a schedule rule is a whole number from 1 to 999, without leading zeros.');
        }

        return (int) $word;
    }

    /**
     * @param list<string> $words
     *
     * @return list<int>
     *
     * @throws InvalidSchedule
     */
    private static function weekdays(array $words): array
    {
        $days = [];

        foreach (explode(',', implode(',', $words)) as $name) {
            if ('' === $name) {
                continue;
            }

            $days[] = self::WEEKDAYS[$name] ?? throw InvalidSchedule::because('A day of the week is written mon, tue, wed, thu, fri, sat or sun.');
        }

        return $days;
    }
}
