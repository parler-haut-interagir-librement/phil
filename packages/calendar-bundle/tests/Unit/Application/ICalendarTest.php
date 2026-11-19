<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Application\ICalendar;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ICalendar::class)]
final class ICalendarTest extends TestCase
{
    public function testEventsAreAnICalendarFile(): void
    {
        $zone = new DateTimeZone('America/Toronto');
        $evening = Event::schedule('Soirée; tous, bienvenus', new DateTimeImmutable('2026-11-10 19:00', $zone), new DateTimeImmutable('2026-11-10 22:00', $zone));
        $evening->repeat(RecurrenceRule::parse('FREQ=MONTHLY;BYDAY=2TU;COUNT=4'));
        $evening->except(new DateTimeImmutable('2026-12-08 19:00', $zone));
        $evening->except(new DateTimeImmutable('2027-01-12 19:00', $zone));
        $evening->addDate(new DateTimeImmutable('2026-12-26 18:00', $zone));
        $evening->addDate(new DateTimeImmutable('2026-12-23 19:00', $zone));
        $evening->describe("Apportez\\vos jeux.\nGratuit.", 'Café X, Montréal', 'https://example.com/jeux?a=1;b=2');
        $evening->tag(['jeux', 'a,b']);

        $festival = Event::schedule('Festival', new DateTimeImmutable('2026-07-10', $zone), new DateTimeImmutable('2026-07-12', $zone), true);
        $festival->except(new DateTimeImmutable('2026-07-10', $zone));
        $festival->addDate(new DateTimeImmutable('2026-08-01 15:00', $zone));
        $festival->cancel();

        $utc = Event::schedule('Appel', new DateTimeImmutable('2026-11-10 15:00 UTC'), new DateTimeImmutable('2026-11-10 16:00 UTC'), timezone: 'UTC');
        $utc->except(new DateTimeImmutable('2026-11-10 15:00 UTC'));
        $utc->addDate(new DateTimeImmutable('2026-11-11 09:30', $zone));

        self::assertSame(implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//PHIL//phil/calendar-bundle//EN',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:Le Club\, calendrier',
            'BEGIN:VEVENT',
            'UID:'.$evening->id,
            'DTSTAMP:20261103T120000Z',
            'DTSTART;TZID=America/Toronto:20261110T190000',
            'DTEND;TZID=America/Toronto:20261110T220000',
            'RRULE:FREQ=MONTHLY;COUNT=4;BYDAY=2TU',
            'RDATE;TZID=America/Toronto:20261223T190000,20261226T180000',
            'EXDATE;TZID=America/Toronto:20261208T190000,20270112T190000',
            'SUMMARY:Soirée\; tous\, bienvenus',
            'DESCRIPTION:Apportez\\\\vos jeux.\nGratuit.',
            'LOCATION:Café X\, Montréal',
            'URL:https://example.com/jeux?a=1;b=2',
            'CATEGORIES:jeux,a\,b',
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:'.$festival->id,
            'DTSTAMP:20261103T120000Z',
            'DTSTART;VALUE=DATE:20260710',
            'DTEND;VALUE=DATE:20260713',
            'RDATE;VALUE=DATE:20260801',
            'EXDATE;VALUE=DATE:20260710',
            'SUMMARY:Festival',
            'STATUS:CANCELLED',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:'.$utc->id,
            'DTSTAMP:20261103T120000Z',
            'DTSTART:20261110T150000Z',
            'DTEND:20261110T160000Z',
            'RDATE:20261111T143000Z',
            'EXDATE:20261110T150000Z',
            'SUMMARY:Appel',
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ])."\r\n", ICalendar::of([$evening, $festival, $utc], new DateTimeImmutable('2026-11-03 07:00', $zone), name: 'Le Club, calendrier'));
    }

    public function testTheEndOfARuleIsOfTheKindOfTheStart(): void
    {
        $zone = new DateTimeZone('America/Toronto');
        $file = static function (Event $event, string $rule): string {
            $event->repeat(RecurrenceRule::parse($rule));
            preg_match('/^RRULE:.*$/m', ICalendar::of([$event], new DateTimeImmutable('2026-11-03 12:00 UTC')), $match);

            return rtrim($match[0] ?? '', "\r");
        };
        $timed = static fn (): Event => Event::schedule('Soirée', new DateTimeImmutable('2026-11-10 19:00', $zone), new DateTimeImmutable('2026-11-10 22:00', $zone));
        $allDay = static fn (): Event => Event::schedule('Festival', new DateTimeImmutable('2026-07-10', $zone), new DateTimeImmutable('2026-07-12', $zone), true);

        self::assertSame('RRULE:FREQ=WEEKLY;UNTIL=20270101T045959Z;BYDAY=TU', $file($timed(), 'FREQ=WEEKLY;UNTIL=20261231;BYDAY=TU'), "the last moment of the end day in the event's zone, in UTC");
        self::assertSame('RRULE:FREQ=WEEKLY;UNTIL=20270701T035959Z', $file($timed(), 'FREQ=WEEKLY;UNTIL=20270630'), 'in summer time');
        self::assertSame('RRULE:FREQ=WEEKLY;UNTIL=20261231T120000Z', $file($timed(), 'FREQ=WEEKLY;UNTIL=20261231T120000Z'));
        self::assertSame('RRULE:FREQ=YEARLY;UNTIL=20301231', $file($allDay(), 'FREQ=YEARLY;UNTIL=20301231'));
        self::assertSame('RRULE:FREQ=YEARLY;UNTIL=20301231', $file($allDay(), 'FREQ=YEARLY;UNTIL=20310101T045959Z'), "the day the end time falls on in the event's zone");
        self::assertSame('RRULE:FREQ=YEARLY;INTERVAL=2;COUNT=3;BYMONTH=7', $file($allDay(), 'FREQ=YEARLY;INTERVAL=2;COUNT=3;BYMONTH=7'));

        $utc = Event::schedule('Appel', new DateTimeImmutable('2026-11-10 15:00 UTC'), new DateTimeImmutable('2026-11-10 16:00 UTC'), timezone: 'UTC');
        self::assertSame('RRULE:FREQ=MONTHLY;INTERVAL=2;UNTIL=20271231T235959Z;BYDAY=2TU;WKST=SU', $file($utc, 'FREQ=MONTHLY;INTERVAL=2;UNTIL=20271231;BYDAY=2TU;WKST=SU'));
    }

    public function testAProductIsNamedAndLongLinesAreFolded(): void
    {
        $zone = new DateTimeZone('UTC');
        $event = Event::schedule(str_repeat('a', 61).'é'.str_repeat('b', 80), new DateTimeImmutable('2026-11-10 15:00', $zone), new DateTimeImmutable('2026-11-10 16:00', $zone));
        $lines = explode("\r\n", ICalendar::of([$event], new DateTimeImmutable('2026-11-03 12:00', $zone), '-//Example//Shop//FR'));

        self::assertSame('PRODID:-//Example//Shop//FR', $lines[2]);
        self::assertNotContains('X-WR-CALNAME', array_map(static fn (string $line): string => explode(':', $line)[0], $lines));
        self::assertSame(['SUMMARY:'.str_repeat('a', 61).'é'.str_repeat('b', 4), ' '.str_repeat('b', 74), ' bb'], \array_slice($lines, 9, 3));
        $straddling = Event::schedule(str_repeat('a', 66).'é'.str_repeat('b', 71).'écc', new DateTimeImmutable('2026-11-10 15:00', $zone), new DateTimeImmutable('2026-11-10 16:00', $zone));
        self::assertSame(['SUMMARY:'.str_repeat('a', 66), ' é'.str_repeat('b', 71), ' écc'], \array_slice(explode("\r\n", ICalendar::of([$straddling], new DateTimeImmutable())), 9, 3));
        self::assertSame("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//PHIL//phil/calendar-bundle//EN\r\nCALSCALE:GREGORIAN\r\nEND:VCALENDAR\r\n", ICalendar::of([], new DateTimeImmutable()));
        self::assertSame('text/calendar', ICalendar::MEDIA_TYPE);

        $broken = Event::schedule("Un\rdeux\r\ntrois\nquatre\x07;", new DateTimeImmutable('2026-11-10 15:00', $zone), new DateTimeImmutable('2026-11-10 16:00', $zone));
        self::assertSame('SUMMARY:Un\\ndeux\\ntrois\\nquatre\\;', explode("\r\n", ICalendar::of([$broken], new DateTimeImmutable()))[9], 'every line break escaped, no control character');
        $controls = implode('', array_map(chr(...), [...range(0, 9), 11, 12, ...range(14, 31), 127]));
        $kept = Event::schedule('a'.$controls.'~Ā€b', new DateTimeImmutable('2026-11-10 15:00', $zone), new DateTimeImmutable('2026-11-10 16:00', $zone));
        self::assertSame('SUMMARY:a~Ā€b', explode("\r\n", ICalendar::of([$kept], new DateTimeImmutable()))[9], 'every control character left out, the rest kept');
    }
}
