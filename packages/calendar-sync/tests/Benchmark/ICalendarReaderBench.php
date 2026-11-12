<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Phil\CalendarSync\BusyPeriods;
use Phil\CalendarSync\ICalendar\ICalendarReader;
use Phil\CalendarSync\SyncWindow;

/**
 * A worker refreshes the busy times of every linked calendar on a schedule: each refresh reads a whole feed — events,
 * weekly rules with dates taken away, long descriptions skipped — and joins its periods.
 *
 * @Groups({"calendar-sync"})
 */
final readonly class ICalendarReaderBench
{
    /** @var array<int, string> by the number of events */
    private array $feeds;

    private SyncWindow $window;

    private DateTimeZone $zone;

    public function __construct()
    {
        $this->window = SyncWindow::days(new DateTimeImmutable('2026-11-01T00:00:00Z'), 60);
        $this->zone = new DateTimeZone('America/Toronto');
        $feeds = [];

        foreach ([100, 1000, 5000] as $count) {
            $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0'];

            for ($i = 0; $i < $count; ++$i) {
                $start = new DateTimeImmutable('2026-10-01 08:00', $this->zone)->modify(\sprintf('+%d hours', 7 * $i));
                $lines[] = 'BEGIN:VEVENT';
                $lines[] = 'UID:'.$i.'@example.com';
                $lines[] = 'DTSTART;TZID=America/Toronto:'.$start->format('Ymd\THis');
                $lines[] = 'DURATION:PT1H';
                $lines[] = 'SUMMARY:Meeting '.$i;
                $lines[] = 'DESCRIPTION:'.str_repeat('Notes. ', 40);
                if (0 === $i % 10) {
                    $lines[] = 'RRULE:FREQ=WEEKLY;COUNT=20';
                    $lines[] = 'EXDATE;TZID=America/Toronto:'.$start->modify('+1 week')->format('Ymd\THis');
                }

                $lines[] = 'END:VEVENT';
            }

            $lines[] = 'END:VCALENDAR';
            $feeds[$count] = implode("\r\n", $lines)."\r\n";
        }

        $this->feeds = $feeds;
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
    public function benchRead(array $params): void
    {
        BusyPeriods::merge(ICalendarReader::busy($this->feeds[$params['events']], $this->window, $this->zone), $this->window);
    }

    /**
     * @return Generator<string, array{events: int}>
     */
    public function counts(): Generator
    {
        yield '100 events' => ['events' => 100];
        yield '1000 events' => ['events' => 1000];
        yield '5000 events' => ['events' => 5000];
    }
}
