<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application\View;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\CalendarBundle\Application\View\CalendarView;
use Phil\CalendarBundle\Application\View\Period;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Period::class)]
#[CoversClass(CalendarView::class)]
final class PeriodTest extends ViewTestCase
{
    public function testEachView(): void
    {
        $date = new DateTimeImmutable('2026-09-16 03:00 UTC');

        self::assertSame(['2026-08-31', '2026-10-05', '2026-09-15'], $this->bounds(Period::of(CalendarView::Month, $date, $this->zone)), 'Monday weeks around September; the 16th at 03:00 UTC is the 15th in Toronto');
        self::assertSame(['2026-08-30', '2026-10-04', '2026-09-15'], $this->bounds(Period::of(CalendarView::Month, $date, $this->zone, 7)), 'Sunday weeks');
        self::assertSame(['2026-09-14', '2026-09-21', '2026-09-15'], $this->bounds(Period::of(CalendarView::Week, $date, $this->zone)));
        self::assertSame(['2026-09-13', '2026-09-20', '2026-09-15'], $this->bounds(Period::of(CalendarView::Week, $date, $this->zone, 7)));
        self::assertSame(['2026-09-15', '2026-09-16', '2026-09-15'], $this->bounds(Period::of(CalendarView::Day, $date, $this->zone)));
        self::assertSame(['2026-09-01', '2026-10-01', '2026-09-15'], $this->bounds(Period::of(CalendarView::List, $date, $this->zone)));
        self::assertSame(['2026-09-01', '2026-10-01', '2026-09-15'], $this->bounds(Period::of(CalendarView::Posters, $date, $this->zone)));
        self::assertSame(['2026-01-26', '2026-03-02', '2026-02-11'], $this->bounds(Period::of(CalendarView::Month, $this->day('2026-02-11'), $this->zone)), 'a February of four weeks and one');
        self::assertSame('00:00 America/Toronto', Period::of(CalendarView::Day, $date, $this->zone)->from->format('H:i e'));
        self::assertSame(['month', 'week', 'day', 'list', 'posters'], array_map(static fn (CalendarView $view): string => $view->value, CalendarView::cases()));
    }

    public function testThePeriodsAroundAndOtherViews(): void
    {
        $month = Period::of(CalendarView::Month, $this->day('2026-01-31'), $this->zone);

        self::assertSame(['2026-02-28', '2025-12-31'], [$month->next()->date->format('Y-m-d'), $month->previous()->date->format('Y-m-d')], 'the 31st, held in a shorter month');
        self::assertSame('2026-02-07', Period::of(CalendarView::Week, $this->day('2026-01-31'), $this->zone)->next()->date->format('Y-m-d'));
        self::assertSame('2026-01-24', Period::of(CalendarView::Week, $this->day('2026-01-31'), $this->zone)->previous()->date->format('Y-m-d'));
        self::assertSame('2026-02-01', Period::of(CalendarView::Day, $this->day('2026-01-31'), $this->zone)->next()->date->format('Y-m-d'));
        self::assertSame('2026-03-01', Period::of(CalendarView::List, $this->day('2026-02-01'), $this->zone)->next()->from->format('Y-m-d'));
        self::assertSame('2026-01-01', Period::of(CalendarView::Posters, $this->day('2026-02-01'), $this->zone)->previous()->from->format('Y-m-d'));
        self::assertSame([CalendarView::Week, '2026-01-26'], [$month->in(CalendarView::Week)->view, $month->in(CalendarView::Week)->from->format('Y-m-d')]);
        self::assertSame(7, Period::of(CalendarView::Week, $this->day('2026-01-31'), $this->zone, 7)->next()->firstWeekday);
        self::assertSame(7, Period::of(CalendarView::Month, $this->day('2026-01-31'), $this->zone, 7)->in(CalendarView::Day)->firstWeekday);
        self::assertSame('2026-04-28', Period::of(CalendarView::Month, $this->day('2026-02-28'), $this->zone)->next()->next()->date->format('Y-m-d'));
        self::assertSame('2026-02-28', Period::of(CalendarView::List, $this->day('2026-03-31'), $this->zone)->previous()->date->format('Y-m-d'));
    }

    public function testTheDaysAndTheMonth(): void
    {
        $month = Period::of(CalendarView::Month, $this->day('2026-03-08'), $this->zone);

        self::assertCount(42, $month->days(), 'March 2026 starts on a Sunday: six weeks');
        self::assertSame(['2026-03-08 00:00', '2026-03-09 00:00'], [$month->days()[13]->format('Y-m-d H:i'), $month->days()[14]->format('Y-m-d H:i')], 'midnights across the change of time');
        self::assertSame([false, true, true, false], [$month->holds($this->day('2026-02-28')), $month->holds($this->day('2026-03-01')), $month->holds($this->day('2026-03-31')), $month->holds($this->day('2026-04-01'))]);
        self::assertCount(1, Period::of(CalendarView::Day, $this->day('2026-03-08'), $this->zone)->days());
    }

    public function testAWeekStartsOnADay(): void
    {
        foreach ([0, 8] as $first) {
            try {
                Period::of(CalendarView::Week, $this->day('2026-03-08'), $this->zone, $first);
                self::fail((string) $first);
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(\sprintf('A week starts on day 1, Monday, to 7, Sunday; not %d.', $first), $invalidArgumentException->getMessage());
            }
        }
    }

    /**
     * @return array{string, string, string}
     */
    private function bounds(Period $period): array
    {
        return [$period->from->format('Y-m-d'), $period->until->format('Y-m-d'), $period->date->format('Y-m-d')];
    }
}
