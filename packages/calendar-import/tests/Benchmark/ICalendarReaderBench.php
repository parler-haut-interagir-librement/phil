<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Tests\Benchmark;

use DateTimeZone;
use Phil\CalendarImport\ICalendar\ICalendarReader;

/**
 * Every scheduled refresh reads a whole feed again: a calendar of 2,000 events — in a named zone, each with a long
 * folded description, categories, and one event in ten repeating with an exception — is read once. The time must stay
 * in proportion to the size of the feed.
 *
 * @BeforeMethods("setUp")
 */
final class ICalendarReaderBench
{
    private readonly ICalendarReader $reader;

    private string $text = '';

    public function __construct()
    {
        $this->reader = new ICalendarReader();
    }

    public function setUp(): void
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Example//Bench//EN', 'X-WR-TIMEZONE:America/Toronto'];

        for ($i = 0; $i < 2_000; ++$i) {
            $day = \sprintf('2026%02d%02d', 1 + intdiv($i, 28) % 12, 1 + $i % 28);
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = \sprintf('UID:event-%d@calendar.example.org', $i);
            $lines[] = 'SEQUENCE:'.($i % 3);
            $lines[] = 'DTSTAMP:20261101T120000Z';
            $lines[] = 'LAST-MODIFIED:20261101T120000Z';
            $lines[] = \sprintf('DTSTART;TZID=America/Toronto:%sT190000', $day);
            $lines[] = 'DURATION:PT2H30M';
            $lines[] = \sprintf('SUMMARY:Evening number %d\, with friends', $i);
            $lines[] = "DESCRIPTION:A long description that goes on for a while\\nwith line breaks\\, commas and\r\n  folded lines\\; so that unfolding and unescaping both have work to do.";
            $lines[] = 'LOCATION:Main hall\, second floor';
            $lines[] = 'CATEGORIES:games,community,evening';

            if (0 === $i % 10) {
                $lines[] = 'RRULE:FREQ=WEEKLY;COUNT=10';
                $lines[] = \sprintf('EXDATE;TZID=America/Toronto:%sT190000', $day);
            }

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        $this->text = implode("\r\n", $lines)."\r\n";
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchReadFeed(): void
    {
        $this->reader->read($this->text, new DateTimeZone('UTC'));
    }
}
