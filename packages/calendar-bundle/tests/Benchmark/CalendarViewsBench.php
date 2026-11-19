<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\View\CalendarView;
use Phil\CalendarBundle\Application\View\CalendarViews;
use Phil\CalendarBundle\Application\View\EventItems;
use Phil\CalendarBundle\Application\View\Items;
use Phil\CalendarBundle\Tests\Benchmark\Fixture\SampleEvents;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryVenues;

/**
 * Every Live Component render lays a view out in PHP from the occurrences of its period, expanded from each event's
 * rule: the month grid in lanes, the week by the hour, the list by days.
 *
 * @Groups({"calendar"})
 */
final class CalendarViewsBench
{
    /**
     * @var array<int, CalendarViews>
     */
    private array $views = [];

    private readonly DateTimeImmutable $date;

    public function __construct()
    {
        $this->date = new DateTimeImmutable('2026-11-12', new DateTimeZone('America/Toronto'));

        foreach ([10, 100, 300] as $count) {
            $events = SampleEvents::repository($count);
            $this->views[$count] = new CalendarViews(new Items([new EventItems(new Calendar($events), $events)]), new InMemoryVenues(), 'America/Toronto');
        }
    }

    /**
     * @ParamProviders("counts")
     *
     * @Revs(1)
     *
     * @Iterations(5)
     *
     * @param array{events: int} $params
     */
    public function benchMonth(array $params): void
    {
        $views = $this->views[$params['events']];
        $views->month($views->period(CalendarView::Month, $this->date));
    }

    /**
     * @ParamProviders("counts")
     *
     * @Revs(2)
     *
     * @Iterations(5)
     *
     * @param array{events: int} $params
     */
    public function benchWeek(array $params): void
    {
        $views = $this->views[$params['events']];
        $views->time($views->period(CalendarView::Week, $this->date));
    }

    /**
     * @ParamProviders("counts")
     *
     * @Revs(2)
     *
     * @Iterations(5)
     *
     * @param array{events: int} $params
     */
    public function benchWeekSharedByTags(array $params): void
    {
        $views = $this->views[$params['events']];
        $views->time($views->period(CalendarView::Week, $this->date), shared: CalendarViews::TAGS);
    }

    /**
     * @ParamProviders("counts")
     *
     * @Revs(2)
     *
     * @Iterations(5)
     *
     * @param array{events: int} $params
     */
    public function benchList(array $params): void
    {
        $views = $this->views[$params['events']];
        $views->agenda($views->period(CalendarView::List, $this->date));
    }

    /**
     * @return Generator<string, array{events: int}>
     */
    public function counts(): Generator
    {
        yield '10 events' => ['events' => 10];
        yield '100 events' => ['events' => 100];
        yield '300 events' => ['events' => 300];
    }
}
