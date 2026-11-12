<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Tests\Unit\ICalendar;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarSync\ICalendar\ICalendarReader;
use Phil\CalendarSync\ICalendar\ICalendarWriter;
use Phil\CalendarSync\OutboundEvent;
use Phil\CalendarSync\SyncWindow;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ICalendarWriter::class)]
final class ICalendarWriterTest extends TestCase
{
    public function testATimedEventIsWrittenInUtcWithItsTextsEscaped(): void
    {
        $event = new OutboundEvent(
            'booking-1@example.com',
            'Session; one, two',
            new DateTimeImmutable('2026-11-10 19:00', new DateTimeZone('America/Toronto')),
            new DateTimeImmutable('2026-11-10 22:00', new DateTimeZone('America/Toronto')),
            description: "Line one\r\nLine two\rThree\\\x07",
            location: 'Room 1',
            url: Url::fromString('https://example.com/b/1'),
            sequence: 3,
        );

        self::assertSame(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//PHIL//phil/calendar-sync//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\n"
            ."UID:booking-1@example.com\r\nDTSTAMP:20261101T130000Z\r\nDTSTART:20261111T000000Z\r\nDTEND:20261111T030000Z\r\n"
            ."SEQUENCE:3\r\nSUMMARY:Session\\; one\\, two\r\nDESCRIPTION:Line one\\nLine two\\nThree\\\\\r\nLOCATION:Room 1\r\n"
            ."URL:https://example.com/b/1\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
            ICalendarWriter::event($event, new DateTimeImmutable('2026-11-01 08:00', new DateTimeZone('America/Toronto'))),
        );
    }

    public function testAWholeDayEventIsWrittenAsDaysAndReadBack(): void
    {
        $event = new OutboundEvent('d', 'Day', new DateTimeImmutable('2026-11-10'), new DateTimeImmutable('2026-11-12'), true);
        $text = ICalendarWriter::event($event, new DateTimeImmutable('2026-11-01T00:00:00Z'), '-//Example//EN');

        self::assertStringContainsString("PRODID:-//Example//EN\r\n", $text);
        self::assertStringContainsString("DTSTART;VALUE=DATE:20261110\r\nDTEND;VALUE=DATE:20261112\r\nSEQUENCE:0\r\nSUMMARY:Day\r\nEND:VEVENT", $text);
        $periods = ICalendarReader::busy($text, SyncWindow::days(new DateTimeImmutable('2026-11-01T00:00:00Z'), 30), new DateTimeZone('UTC'));
        self::assertSame('2026-11-10 2026-11-12', $periods[0]->start->format('Y-m-d').' '.$periods[0]->end->format('Y-m-d'));
    }

    public function testLongLinesAreFoldedBetweenCharacters(): void
    {
        $title = str_repeat('é', 100);
        $text = ICalendarWriter::event(new OutboundEvent('u', $title, new DateTimeImmutable('2026-11-10T12:00:00Z'), new DateTimeImmutable('2026-11-10T13:00:00Z')), new DateTimeImmutable('2026-11-01T00:00:00Z'));

        foreach (explode("\r\n", $text) as $line) {
            self::assertLessThanOrEqual(75, \strlen($line));
            self::assertTrue(mb_check_encoding($line, 'UTF-8'));
        }

        $lines = explode("\r\n", $text);
        $summary = array_first(array_filter($lines, static fn (string $line): bool => str_starts_with($line, 'SUMMARY:')));
        self::assertIsString($summary);
        self::assertSame(74, \strlen($summary));
        self::assertStringContainsString('SUMMARY:'.$title, preg_replace('/\r\n /', '', $text) ?? '');
        self::assertMatchesRegularExpression('/SUMMARY:é{33}\r\n é{37}\r\n é{30}\r\n/u', $text);
    }

    public function testLongLinesAreFoldedAtSeventyFiveBytes(): void
    {
        $text = $this->title(str_repeat('a', 200));
        $lines = explode("\r\n", $text);
        $at = array_search('SUMMARY:'.str_repeat('a', 67), $lines, true);

        self::assertIsInt($at);
        self::assertSame([75, 75, 60], array_map(strlen(...), \array_slice($lines, $at, 3)));
        self::assertSame([' '.str_repeat('a', 74), ' '.str_repeat('a', 59)], \array_slice($lines, $at + 1, 2));
    }

    public function testLongLinesOfThreeByteCharactersAreNeverCut(): void
    {
        $title = str_repeat('€', 100);
        $text = $this->title($title);

        foreach (explode("\r\n", $text) as $line) {
            self::assertLessThanOrEqual(75, \strlen($line));
            self::assertTrue(mb_check_encoding($line, 'UTF-8'));
        }

        self::assertStringContainsString('SUMMARY:'.$title, preg_replace('/\r\n /', '', $text) ?? '');
        self::assertMatchesRegularExpression('/SUMMARY:€{22}\r\n €{24}\r\n €{24}\r\n €{24}\r\n €{6}\r\n/u', $text);
    }

    private function title(string $title): string
    {
        return ICalendarWriter::event(new OutboundEvent('u', $title, new DateTimeImmutable('2026-11-10T12:00:00Z'), new DateTimeImmutable('2026-11-10T13:00:00Z')), new DateTimeImmutable('2026-11-01T00:00:00Z'));
    }
}
