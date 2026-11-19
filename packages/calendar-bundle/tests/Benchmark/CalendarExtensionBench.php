<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\CalendarBundle\Infrastructure\Twig\CalendarExtension;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;

/**
 * The month and week templates write the time of every item with `calendar_time`, the list writes each occurrence
 * with `occurrence_when`, and headers use `calendar_format`; a busy month makes hundreds of calls per render.
 *
 * @Groups({"calendar"})
 */
final class CalendarExtensionBench
{
    private readonly CalendarExtension $extension;

    /**
     * @var list<Occurrence>
     */
    private array $occurrences = [];

    public function __construct()
    {
        $zone = new DateTimeZone('America/Toronto');
        $this->extension = new CalendarExtension(new Calendar(new InMemoryEvents()));
        $start = new DateTimeImmutable('2026-11-10 19:00', $zone);
        $event = Event::schedule('Game night', $start, $start->modify('+2 hours'));

        for ($index = 0; $index < 500; ++$index) {
            $at = $start->modify(\sprintf('+%d hours', $index * 5));
            $this->occurrences[] = new Occurrence($event, $at, $at->modify('+2 hours'));
        }
    }

    /**
     * @ParamProviders("counts")
     *
     * @Revs(3)
     *
     * @Iterations(5)
     *
     * @param array{items: int} $params
     */
    public function benchTime(array $params): void
    {
        foreach (\array_slice($this->occurrences, 0, $params['items']) as $occurrence) {
            $this->extension->time($occurrence->start, 'fr_CA');
        }
    }

    /**
     * @ParamProviders("counts")
     *
     * @Revs(3)
     *
     * @Iterations(5)
     *
     * @param array{items: int} $params
     */
    public function benchWhen(array $params): void
    {
        foreach (\array_slice($this->occurrences, 0, $params['items']) as $occurrence) {
            $this->extension->when($occurrence, 'fr_CA');
        }
    }

    /**
     * @ParamProviders("counts")
     *
     * @Revs(3)
     *
     * @Iterations(5)
     *
     * @param array{items: int} $params
     */
    public function benchPattern(array $params): void
    {
        foreach (\array_slice($this->occurrences, 0, $params['items']) as $occurrence) {
            $this->extension->pattern($occurrence->start, 'EEE d', 'fr_CA');
        }
    }

    /**
     * @return Generator<string, array{items: int}>
     */
    public function counts(): Generator
    {
        yield '1 item' => ['items' => 1];
        yield '50 items' => ['items' => 50];
        yield '500 items' => ['items' => 500];
    }
}
