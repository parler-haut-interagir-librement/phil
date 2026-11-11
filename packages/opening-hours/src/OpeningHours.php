<?php

declare(strict_types=1);

namespace Phil\OpeningHours;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Phil\OpeningHours\Exception\InvalidOpeningHours;

/**
 * A place's hours: the week's, the special hours that replace them on some days, and the time zone they are kept in.
 * Whether the place is open, and when it next opens, is asked at a moment in any time zone.
 */
final readonly class OpeningHours
{
    /** How many days after a moment's own `nextOpening()` looks at: a year, a leap one included. */
    private const int AHEAD = 366;

    /**
     * @param array<int, DayHours> $week     by ISO day number, every day
     * @param list<SpecialHours>   $specials
     */
    private function __construct(
        private array $week,
        public array $specials,
        public string $timezone,
    ) {
    }

    /**
     * @param array<int, DayHours> $week     by ISO day number, Monday is 1; a missing day is closed
     * @param list<SpecialHours>   $specials the first that covers a day wins
     */
    public static function of(array $week, array $specials = [], string $timezone = 'UTC'): self
    {
        try {
            new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidOpeningHours::unreadable($timezone, 'a time zone');
        }

        $days = [];

        foreach (DayOfWeek::cases() as $day) {
            $days[$day->value] = $week[$day->value] ?? DayHours::closed();
        }

        return new self($days, $specials, $timezone);
    }

    public function on(DayOfWeek $day): DayHours
    {
        return $this->week[$day->value];
    }

    /**
     * The special hours of a day, or `null` when it keeps the week's.
     */
    public function specialOn(DateTimeInterface $day): ?SpecialHours
    {
        return array_find($this->specials, static fn (SpecialHours $special): bool => $special->covers($day));
    }

    /**
     * The hours of a calendar day: its special hours, or its weekday's.
     */
    public function hoursOn(DateTimeInterface $day): DayHours
    {
        return $this->specialOn($day)->hours ?? $this->on(DayOfWeek::of($day));
    }

    public function isOpenAt(DateTimeInterface $moment): bool
    {
        $local = $this->local($moment);
        $minute = $this->minuteOf($local);

        return array_any($this->hoursOn($local)->ranges, static fn (TimeRange $range): bool => $range->contains($minute))
            || array_any($this->hoursOn($local->modify('-1 day'))->ranges, static fn (TimeRange $range): bool => $range->spills($minute));
    }

    /**
     * When the place next opens after a moment, in its time zone; `null` when it does not within a year.
     */
    public function nextOpening(DateTimeInterface $moment): ?DateTimeImmutable
    {
        $local = $this->local($moment);
        $minute = $this->minuteOf($local);
        $specials = $this->specialsAhead($local);
        $weekday = DayOfWeek::of($local)->value;
        // A place the week keeps closed opens on a special day only: those are the days to look at.
        $offsets = $this->isAlwaysClosed() ? array_keys($specials) : range(0, self::AHEAD);

        foreach ($offsets as $offset) {
            foreach (($specials[$offset] ?? $this->week[($weekday + $offset - 1) % 7 + 1])->ranges as $range) {
                if ($offset > 0 || $range->opens->minutes > $minute) {
                    return $local->modify(\sprintf('+%d days', $offset))->setTime($range->opens->hours(), $range->opens->minutes % 60);
                }
            }
        }

        return null;
    }

    /**
     * The week as runs of consecutive days with the same hours, from Monday: what a table of hours shows.
     *
     * @return list<array{days: non-empty-list<DayOfWeek>, hours: DayHours}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (DayOfWeek::cases() as $day) {
            $last = array_key_last($groups);

            if (null !== $last && $groups[$last]['hours']->equals($this->on($day))) {
                $groups[$last]['days'][] = $day;
            } else {
                $groups[] = ['days' => [$day], 'hours' => $this->on($day)];
            }
        }

        return $groups;
    }

    public function isAlwaysClosed(): bool
    {
        return array_all($this->week, static fn (DayHours $hours): bool => $hours->isClosed());
    }

    /**
     * The hours of the special days among the day of a moment and the 366 after it, by their distance from that day
     * in days, in order: each period read once, the first special that covers a day winning.
     *
     * @return array<int, DayHours>
     */
    private function specialsAhead(DateTimeImmutable $local): array
    {
        $utc = new DateTimeZone('UTC');
        $today = new DateTimeImmutable($local->format('Y-m-d'), $utc)->getTimestamp();
        $days = [];

        foreach ($this->specials as $special) {
            $start = intdiv(new DateTimeImmutable($special->from, $utc)->getTimestamp() - $today, 86_400);
            // A single day is read once.
            $end = $special->through === $special->from ? $start : intdiv(new DateTimeImmutable($special->through, $utc)->getTimestamp() - $today, 86_400);
            $through = min(self::AHEAD, $end);

            for ($offset = max(0, $start); $offset <= $through; ++$offset) {
                $days[$offset] ??= $special->hours;
            }
        }

        ksort($days);

        return $days;
    }

    private function local(DateTimeInterface $moment): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone($this->timezone));
    }

    private function minuteOf(DateTimeImmutable $moment): int
    {
        // The wall clock's second of the day, before 1970 too.
        $second = (($moment->getTimestamp() + $moment->getOffset()) % 86400 + 86400) % 86400;

        return intdiv($second, 60);
    }
}
