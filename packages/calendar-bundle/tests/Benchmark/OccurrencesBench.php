<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Tests\Benchmark\Fixture\SampleEvents;
use Phil\Recurrence\RecurrenceRule;

/**
 * Every render of the calendar component and every poll of `calendar.ics` asks each event of the period for its
 * occurrences; the cost is the number of events times what one series costs to read for a month.
 *
 * @Groups({"calendar"})
 */
final class OccurrencesBench
{
    /**
     * @var array<int, Event>
     */
    private array $events = [];

    /**
     * @var array<int, Calendar>
     */
    private array $calendars = [];

    private readonly DateTimeImmutable $from;

    private readonly DateTimeImmutable $through;

    public function __construct()
    {
        $zone = new DateTimeZone('America/Toronto');
        $this->from = new DateTimeImmutable('2026-11-01 00:00', $zone);
        $this->through = new DateTimeImmutable('2026-11-30 23:59:59', $zone);

        foreach ([0, 10, 30] as $age) {
            $start = new DateTimeImmutable(\sprintf('%d-01-06 19:00', 2026 - $age), $zone);
            $event = Event::schedule('Game night', $start, $start->modify('+3 hours'));
            $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=TU,TH'));
            $this->events[$age] = $event;
        }

        foreach ([10, 100, 500] as $count) {
            $this->calendars[$count] = new Calendar(SampleEvents::repository($count));
        }
    }

    /**
     * @ParamProviders("ages")
     *
     * @Revs(50)
     *
     * @Iterations(5)
     *
     * @param array{age: int} $params
     */
    public function benchEventMonth(array $params): void
    {
        $this->events[$params['age']]->occurrences($this->from, $this->through);
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
    public function benchCalendarMonth(array $params): void
    {
        $this->calendars[$params['events']]->occurrences($this->from, $this->through);
    }

    /**
     * @return Generator<string, array{age: int}>
     */
    public function ages(): Generator
    {
        yield 'series of this year' => ['age' => 0];
        yield 'series 10 years old' => ['age' => 10];
        yield 'series 30 years old' => ['age' => 30];
    }

    /**
     * @return Generator<string, array{events: int}>
     */
    public function counts(): Generator
    {
        yield '10 events' => ['events' => 10];
        yield '100 events' => ['events' => 100];
        yield '500 events' => ['events' => 500];
    }
}
