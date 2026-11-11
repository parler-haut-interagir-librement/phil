<?php

declare(strict_types=1);

namespace Phil\Recurrence;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use LogicException;

/**
 * A rule from its first occurrence, `DTSTART`, in that occurrence's time zone, with the dates `RDATE` adds and without
 * the occurrences `EXDATE` takes away. Occurrences keep the first one's time of day, on the wall clock of its time zone.
 *
 * The first occurrence is always one, and counts in `COUNT`; a taken-away occurrence counts too, as RFC 5545 says. An
 * added date is merged into the occurrences in order, once even when the rule gives it too; it counts in neither
 * `COUNT` nor `UNTIL`, and `EXDATE` takes it away as it does any occurrence.
 */
final readonly class Recurrence
{
    /**
     * No rule walks more periods in a row without a day than this: a rule that matches no day — the 30th of February —
     * stops. A rule that keeps matching days never stops before its own end.
     */
    public const int MAX_PERIODS = 10_000;

    /**
     * No call to `between()` or `next()` walks more periods than this in all, whatever it finds: a rule that matches
     * one day in years — the 29th of February, every day — gives what these periods hold and no more. `limit` caps
     * the occurrences returned; this caps the work. `occurrences()` is walked as far as its caller asks.
     */
    public const int MAX_WALKED = 50_000;

    /** The last year a day is written in with four digits, and read back: the occurrences stop at its end. */
    private const int LAST_YEAR = 9999;

    /** The number of 9999-12-31, the last day of the last year, in days since 1970-01-01. */
    private const int LAST_DAY = 2_932_896;

    private const int SECONDS = 86_400;

    /** The days of a common year before the first of each month. */
    private const array DAYS_BEFORE = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

    /** @var array{int, int, int, int} the first occurrence's time of day: hours, minutes, seconds, microseconds */
    private array $time;

    /** @var list<int> the ISO 8601 weekdays a weekly or daily rule keeps: its own, or the first occurrence's */
    private array $weekdays;

    /** The first occurrence's day of the month, and its month. */
    private int $monthDay;

    private int $month;

    /** @var array<array-key, true> the occurrences taken away, by their instant: read once, not once an occurrence */
    private array $excluded;

    /** @var list<DateTimeImmutable> the dates added and not taken away, in order, once each, in the start's zone */
    private array $added;

    /**
     * @param list<DateTimeInterface> $exclusions
     * @param list<DateTimeInterface> $additions
     */
    private function __construct(
        public DateTimeImmutable $start,
        public RecurrenceRule $rule,
        public array $exclusions,
        public array $additions,
    ) {
        [$hours, $minutes, $seconds, $microseconds, $this->monthDay, $this->month] = array_map(intval(...), explode(':', $start->format('G:i:s:u:j:n')));
        $this->time = [$hours, $minutes, $seconds, $microseconds];
        $this->weekdays = [] === $rule->byDay ? [Weekday::of($start)->iso()] : array_map(static fn (WeekdayNum $num): int => $num->weekday->iso(), $rule->byDay);
        $this->excluded = array_fill_keys(array_map(static fn (DateTimeInterface $exclusion): string => $exclusion->format('U.u'), $exclusions), true);
        $added = [];

        foreach ($additions as $addition) {
            $instant = $addition->format('U.u');

            if (!isset($this->excluded[$instant])) {
                $added[$instant] = DateTimeImmutable::createFromInterface($addition)->setTimezone($start->getTimezone());
            }
        }

        usort($added, static fn (DateTimeImmutable $one, DateTimeImmutable $other): int => $one <=> $other);
        $this->added = $added;
    }

    /**
     * @param list<DateTimeInterface> $exclusions occurrences taken away, `EXDATE`
     * @param list<DateTimeInterface> $additions  dates added to the occurrences, `RDATE`
     */
    public static function of(DateTimeInterface $start, RecurrenceRule $rule, array $exclusions = [], array $additions = []): self
    {
        return new self(DateTimeImmutable::createFromInterface($start), $rule, $exclusions, $additions);
    }

    /**
     * The occurrences that start from one moment through another, at most so many.
     *
     * @return list<DateTimeImmutable>
     */
    public function between(?DateTimeInterface $from = null, ?DateTimeInterface $through = null, int $limit = 1000): array
    {
        $occurrences = [];

        if ($limit < 1) {
            return [];
        }

        foreach ($this->walk($from, $through, self::MAX_WALKED) as $occurrence) {
            if ($through instanceof DateTimeInterface && $occurrence > $through) {
                return $occurrences;
            }

            if (!$from instanceof DateTimeInterface || $occurrence >= $from) {
                $occurrences[] = $occurrence;

                if (\count($occurrences) === $limit) {
                    return $occurrences;
                }
            }
        }

        return $occurrences;
    }

    /**
     * The first occurrence that starts after a moment, or `null` when the rule has ended.
     */
    public function next(DateTimeInterface $after): ?DateTimeImmutable
    {
        foreach ($this->walk($after, null, self::MAX_WALKED) as $occurrence) {
            if ($occurrence > $after) {
                return $occurrence;
            }
        }

        return null;
    }

    /**
     * Every occurrence, in order.
     *
     * @return Generator<int, DateTimeImmutable>
     */
    public function occurrences(): Generator
    {
        return $this->walk();
    }

    /**
     * The occurrences of the rule and the dates added, merged in order: an added date the rule gives too comes once. The
     * added dates after the rule's last occurrence follow it, whatever stopped the rule.
     *
     * @return Generator<int, DateTimeImmutable>
     */
    private function walk(?DateTimeInterface $from = null, ?DateTimeInterface $through = null, ?int $budget = null): Generator
    {
        $next = 0;
        $count = \count($this->added);

        foreach ($this->ruled($from, $through, $budget) as $occurrence) {
            for (; $next < $count && $this->added[$next] <= $occurrence; ++$next) {
                if ($this->added[$next] < $occurrence) {
                    yield $this->added[$next];
                }
            }

            yield $occurrence;
        }

        for (; $next < $count; ++$next) {
            yield $this->added[$next];
        }
    }

    /**
     * The occurrences of the rule, in order, from the period a moment falls in when one is given and no count makes the
     * earlier ones matter, and no further than the period of another moment when one is given, nor than so many periods
     * when a budget is given.
     *
     * @return Generator<int, DateTimeImmutable>
     */
    private function ruled(?DateTimeInterface $from = null, ?DateTimeInterface $through = null, ?int $budget = null): Generator
    {
        $emitted = 0;

        foreach ($this->candidates(null === $this->rule->count ? $from : null, $through, $budget) as $occurrence) {
            if ($this->ended($occurrence) || (null !== $this->rule->count && $emitted >= $this->rule->count)) {
                return;
            }

            ++$emitted;

            if (!$this->excluded($occurrence)) {
                yield $occurrence;
            }
        }
    }

    /**
     * The first occurrence, then the rule's days after it, period by period: from the period of the day before `$from`
     * when it is given, through the period of the day of `$through` when it is given — a period that starts later
     * holds no moment before it. Periods are skipped by arithmetic, never walked, so that the cost of a window does not
     * grow with the age of the rule; no more than `$budget` periods are walked when it is given.
     *
     * @return Generator<int, DateTimeImmutable>
     */
    private function candidates(?DateTimeInterface $from, ?DateTimeInterface $through, ?int $budget): Generator
    {
        yield $this->start;

        $first = $this->anchor($this->day($this->start));
        $cursor = $from instanceof DateTimeInterface ? $this->periodOf($first, $this->day($from)->modify('-1 day')) : $first;
        $last = $through instanceof DateTimeInterface ? $this->day($through)->format('Y-m-d') : null;
        // The periods walked, and how many had been when the last one with a day was: the difference is the periods in
        // a row without a day.
        $walked = 0;
        $lastWithDays = 0;

        while ($walked - $lastWithDays < self::MAX_PERIODS && (null === $budget || $walked < $budget) && $cursor->format('Y') <= self::LAST_YEAR && (null === $last || $cursor->format('Y-m-d') <= $last)) {
            $days = $this->positioned($this->days(intdiv($cursor->getTimestamp(), self::SECONDS)), $this->rule->bySetPos);
            ++$walked;

            if ([] !== $days) {
                $lastWithDays = $walked;
            }

            foreach ($days as $day) {
                if ($day > self::LAST_DAY) {
                    return;
                }

                // Built from the wall clock: a time a change of time skips is the time after it, as RFC 5545 says.
                $occurrence = $this->onDay($day);

                if ($occurrence > $this->start) {
                    yield $occurrence;
                }
            }

            $cursor = $cursor->modify(\sprintf('+%d %s', $this->rule->interval, $this->unit()));
        }
    }

    /**
     * The calendar day of a moment in the first occurrence's time zone, as a UTC midnight.
     */
    private function day(DateTimeInterface $moment): DateTimeImmutable
    {
        $local = DateTimeImmutable::createFromInterface($moment)->setTimezone($this->start->getTimezone());

        return new DateTimeImmutable($local->format('Y-m-d'), new DateTimeZone('UTC'));
    }

    /**
     * The first day of the rule's period that holds a day, counted from the first period by whole intervals; the first
     * period itself when the day is before it.
     */
    private function periodOf(DateTimeImmutable $first, DateTimeImmutable $day): DateTimeImmutable
    {
        $elapsed = $first->diff($day);

        if (1 === $elapsed->invert) {
            return $first;
        }

        // The first period starts on the first of a month for a monthly or yearly rule: whole months and years elapsed
        // are calendar ones.
        $units = match ($this->rule->frequency) {
            Frequency::Yearly => $elapsed->y,
            Frequency::Monthly => $elapsed->y * 12 + $elapsed->m,
            Frequency::Weekly => intdiv($elapsed->days, 7),
            Frequency::Daily => $elapsed->days,
        };

        return $first->modify(\sprintf('+%d %s', intdiv($units, $this->rule->interval) * $this->rule->interval, $this->unit()));
    }

    private function unit(): string
    {
        return match ($this->rule->frequency) {
            Frequency::Yearly => 'years',
            Frequency::Monthly => 'months',
            Frequency::Weekly => 'weeks',
            Frequency::Daily => 'days',
        };
    }

    /**
     * The first day of the period the first occurrence is in.
     */
    private function anchor(DateTimeImmutable $day): DateTimeImmutable
    {
        return match ($this->rule->frequency) {
            Frequency::Yearly => $day->modify('first day of january this year'),
            Frequency::Monthly => $day->modify('first day of this month'),
            Frequency::Weekly => $day->modify(\sprintf('-%d days', (Weekday::of($day)->iso() - $this->rule->weekStart->iso() + 7) % 7)),
            Frequency::Daily => $day,
        };
    }

    private function onDay(int $day): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $this->date('Y-m-d', $day), $this->start->getTimezone());

        if (false === $date) {
            throw new LogicException(\sprintf('The day %s cannot be read back.', $this->date('Y-m-d', $day)));
        }

        return $date->setTime(...$this->time);
    }

    /**
     * The days of a period the rule keeps, in order. Days are numbers — days since 1970-01-01 —, made moments only once
     * kept: a period is laid out without building a date for each of its days.
     *
     * @return list<int> in order
     */
    private function days(int $period): array
    {
        $rule = $this->rule;

        $days = match ($rule->frequency) {
            Frequency::Daily => [$period],
            Frequency::Weekly => array_filter(range($period, $period + 6), fn (int $day): bool => \in_array($this->weekday($day), $this->weekdays, true)),
            Frequency::Monthly => $this->inMonth($period),
            Frequency::Yearly => $this->inYear($period),
        };

        return array_values(array_filter($days, fn (int $day): bool => ([] === $rule->byMonth || \in_array((int) $this->date('n', $day), $rule->byMonth, true))
                && (Frequency::Daily !== $rule->frequency || (
                    ([] === $rule->byMonthDay || $this->isMonthDay($day, $rule->byMonthDay))
                    && ([] === $rule->byDay || \in_array($this->weekday($day), $this->weekdays, true))
                ))));
    }

    /**
     * @return array<int, int> in order
     */
    private function inMonth(int $month): array
    {
        $rule = $this->rule;
        $length = (int) $this->date('t', $month);

        if ([] === $rule->byMonthDay && [] === $rule->byDay) {
            return $this->monthDay <= $length ? [$month + $this->monthDay - 1] : [];
        }

        if ([] === $rule->byDay) {
            return $this->monthDaysOf($month, $length, $rule->byMonthDay);
        }

        $weekdays = $this->weekdaysOf($month, $length, $rule->byDay);

        return [] === $rule->byMonthDay ? $weekdays : array_intersect($this->monthDaysOf($month, $length, $rule->byMonthDay), $weekdays);
    }

    /**
     * The days of a month that are among days of the month, counted from its start or — negative — from its end.
     *
     * @param int       $month     the month's first day
     * @param int       $length    how many days it holds
     * @param list<int> $monthDays
     *
     * @return array<int, int> in order
     */
    private function monthDaysOf(int $month, int $length, array $monthDays): array
    {
        $kept = [];

        foreach ($monthDays as $monthDay) {
            // Counted from the end, -1 is the last day; a day counted from the start lands past the month's length.
            $fromEnd = $length + 1 + $monthDay;
            $date = $fromEnd > $length ? $monthDay : $fromEnd;

            if ($date >= 1 && $date <= $length) {
                $kept[] = $month + $date - 1;
            }
        }

        sort($kept);

        return array_unique($kept);
    }

    /**
     * @return array<int, int> in order
     */
    private function inYear(int $year): array
    {
        $rule = $this->rule;
        $leap = '1' === $this->date('L', $year);

        if ([] !== $rule->byMonth || [] !== $rule->byMonthDay || ([] === $rule->byDay)) {
            $months = [] === $rule->byMonth ? array_filter(range(1, 12), fn (int $month): bool => [] !== $rule->byMonthDay || [] !== $rule->byDay || $month === $this->month) : $rule->byMonth;
            $days = [];

            foreach ($months as $month) {
                array_push($days, ...$this->inMonth($year + self::DAYS_BEFORE[$month - 1] + ($leap && $month > 2 ? 1 : 0)));
            }

            return $days;
        }

        return $this->weekdaysOf($year, $leap ? 366 : 365, $rule->byDay);
    }

    /**
     * The days of a span that are the rule's weekdays, at their place in the span when they have one.
     *
     * @param int              $first    the span's first day
     * @param int              $length   how many days it holds
     * @param list<WeekdayNum> $weekdays
     *
     * @return array<int, int> in order
     */
    private function weekdaysOf(int $first, int $length, array $weekdays): array
    {
        $kept = [];

        foreach ($weekdays as $weekday) {
            // A weekday comes every seven days from its first in the span.
            $matching = range($first + (($weekday->weekday->iso() - $this->weekday($first) + 7) % 7), $first + $length - 1, 7);
            array_push($kept, ...(null === $weekday->ordinal ? $matching : $this->positioned($matching, [$weekday->ordinal])));
        }

        sort($kept);

        return array_unique($kept);
    }

    /**
     * The days at the positions given, from the start or — negative — from the end; all when none is given.
     *
     * @param list<int> $days      in order
     * @param list<int> $positions
     *
     * @return array<int, int> in order
     */
    private function positioned(array $days, array $positions): array
    {
        if ([] === $positions) {
            return $days;
        }

        $kept = [];

        foreach ($positions as $position) {
            $day = $days[$position >= 1 ? $position - 1 : \count($days) + $position] ?? null;

            if (null !== $day) {
                $kept[] = $day;
            }
        }

        sort($kept);

        return array_unique($kept);
    }

    /**
     * @param list<int> $monthDays
     */
    private function isMonthDay(int $day, array $monthDays): bool
    {
        [$date, $length] = array_map(intval(...), explode(' ', $this->date('j t', $day)));

        return \in_array($day, $this->monthDaysOf($day - $date + 1, $length, $monthDays), true);
    }

    /**
     * The ISO 8601 weekday of a day: 1 is Monday, 7 Sunday.
     */
    private function weekday(int $day): int
    {
        return (int) $this->date('N', $day);
    }

    /**
     * A day written with a `date()` format.
     */
    private function date(string $format, int $day): string
    {
        return gmdate($format, $day * self::SECONDS);
    }

    /**
     * Whether an occurrence starts after the rule's end: an end day keeps all its occurrences, in the first one's time
     * zone.
     */
    private function ended(DateTimeImmutable $occurrence): bool
    {
        $until = $this->rule->until;

        return match (true) {
            !$until instanceof DateTimeImmutable => false,
            $this->rule->untilIsDate => $occurrence->format('Y-m-d') > $until->format('Y-m-d'),
            default => $occurrence > $until,
        };
    }

    private function excluded(DateTimeImmutable $occurrence): bool
    {
        return isset($this->excluded[$occurrence->format('U.u')]);
    }
}
