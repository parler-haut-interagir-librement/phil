<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Phil\Recurrence\Weekday;

/**
 * The period a view shows, from a date in it: the weeks that hold a month for its grid, a month for its list and its
 * posters, a week, a day. `from` is included and `until` not; both are midnights in the calendar's time zone — or the
 * first moment of the day, where the clocks skip midnight.
 */
final readonly class Period
{
    private function __construct(
        public CalendarView $view,
        public DateTimeImmutable $date,
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
        public int $firstWeekday,
    ) {
    }

    /**
     * @param int $firstWeekday the day weeks start on, ISO 8601: 1 is Monday, 7 Sunday
     */
    public static function of(CalendarView $view, DateTimeInterface $date, DateTimeZone $zone, int $firstWeekday = 1): self
    {
        if ($firstWeekday < 1 || $firstWeekday > 7) {
            throw new InvalidArgumentException(\sprintf('A week starts on day 1, Monday, to 7, Sunday; not %d.', $firstWeekday));
        }

        $day = new DateTimeImmutable(DateTimeImmutable::createFromInterface($date)->setTimezone($zone)->format('Y-m-d'), $zone);
        $month = $day->modify('first day of this month');
        $weekOf = static fn (DateTimeImmutable $of): DateTimeImmutable => $of->modify(\sprintf('-%d days', (Weekday::of($of)->iso() - $firstWeekday + 7) % 7));

        [$from, $until] = match ($view) {
            CalendarView::Month => [$weekOf($month), $weekOf($month->modify('last day of this month'))->modify('+7 days')],
            CalendarView::Week => [$weekOf($day), $weekOf($day)->modify('+7 days')],
            CalendarView::Day => [$day, $day->modify('+1 day')],
            CalendarView::List, CalendarView::Posters => [$month, $month->modify('+1 month')],
        };

        return new self($view, $day, $from->setTime(0, 0), $until->setTime(0, 0), $firstWeekday);
    }

    /**
     * The period before: the month, the week or the day.
     */
    public function previous(): self
    {
        return self::of($this->view, $this->moved(-1), $this->date->getTimezone(), $this->firstWeekday);
    }

    public function next(): self
    {
        return self::of($this->view, $this->moved(1), $this->date->getTimezone(), $this->firstWeekday);
    }

    /**
     * The same date in another view.
     */
    public function in(CalendarView $view): self
    {
        return self::of($view, $this->date, $this->date->getTimezone(), $this->firstWeekday);
    }

    /**
     * Whether a day is in the month the view is about, for a grid's days of the months around it.
     */
    public function holds(DateTimeInterface $day): bool
    {
        return $day->format('Y-m') === $this->date->format('Y-m');
    }

    /**
     * The days of the period, one midnight each.
     *
     * @return list<DateTimeImmutable>
     */
    public function days(): array
    {
        $days = [];

        for ($day = $this->from; $day < $this->until; $day = self::following($day)) {
            $days[] = $day;
        }

        return $days;
    }

    /**
     * The first moment of the day after: midnight, or the moment after it where the clocks skip midnight — a day added
     * to such a day would carry its hour to every day after.
     */
    public static function following(DateTimeImmutable $day): DateTimeImmutable
    {
        return $day->modify('+1 day')->setTime(0, 0);
    }

    /**
     * The number of a day on the calendar, from its date alone — days since 1970-01-01 —: two days' numbers differ by
     * the days between them, however long those days are. It is counted without building a date.
     */
    public static function dayNumber(DateTimeInterface $day): int
    {
        // The wall clock as seconds since 1970-01-01: its instant with its offset added, counted in whole days.
        return (int) floor(($day->getTimestamp() + $day->getOffset()) / 86_400);
    }

    /**
     * The date a period away: a month away keeps its day, or the last of a shorter month.
     */
    private function moved(int $direction): DateTimeImmutable
    {
        if (CalendarView::Week === $this->view || CalendarView::Day === $this->view) {
            return $this->date->modify(\sprintf('%+d days', $direction * (CalendarView::Week === $this->view ? 7 : 1)));
        }

        $month = $this->date->modify('first day of this month')->modify(\sprintf('%+d month', $direction));

        return $month->setDate((int) $month->format('Y'), (int) $month->format('n'), min((int) $this->date->format('j'), (int) $month->format('t')));
    }
}
