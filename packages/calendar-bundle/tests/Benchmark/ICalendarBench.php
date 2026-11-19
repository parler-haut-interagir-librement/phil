<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Phil\CalendarBundle\Application\ICalendar;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Tests\Benchmark\Fixture\SampleEvents;

/**
 * Subscribers poll the `.ics` feed every few minutes and each poll writes every event again: escaped texts, rules,
 * exceptions and folded lines.
 *
 * @Groups({"calendar"})
 */
final readonly class ICalendarBench
{
    /**
     * @var list<Event>
     */
    private array $events;

    private DateTimeImmutable $stamp;

    public function __construct()
    {
        $this->events = SampleEvents::of(500);
        $this->stamp = new DateTimeImmutable('2026-11-05 12:00', new DateTimeZone('UTC'));
    }

    /**
     * @ParamProviders("counts")
     *
     * @Revs(3)
     *
     * @Iterations(5)
     *
     * @param array{events: int} $params
     */
    public function benchFeed(array $params): void
    {
        ICalendar::of(\array_slice($this->events, 0, $params['events']), $this->stamp, name: 'Événements; jeux, cartes');
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
