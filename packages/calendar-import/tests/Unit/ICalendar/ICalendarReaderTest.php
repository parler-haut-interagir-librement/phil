<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Tests\Unit\ICalendar;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarImport\EventStatus;
use Phil\CalendarImport\Exception\EventRefused;
use Phil\CalendarImport\Exception\InvalidCalendar;
use Phil\CalendarImport\Exception\LimitExceeded;
use Phil\CalendarImport\ICalendar\Component;
use Phil\CalendarImport\ICalendar\ComponentTree;
use Phil\CalendarImport\ICalendar\ContentLine;
use Phil\CalendarImport\ICalendar\ContentLines;
use Phil\CalendarImport\ICalendar\EventReader;
use Phil\CalendarImport\ICalendar\ICalendarReader;
use Phil\CalendarImport\ICalendar\Limit;
use Phil\CalendarImport\ICalendar\ReaderLimits;
use Phil\CalendarImport\ICalendar\ReadResult;
use Phil\CalendarImport\ICalendar\RejectedEvent;
use Phil\CalendarImport\ICalendar\Rejection;
use Phil\CalendarImport\ICalendar\Text;
use Phil\CalendarImport\ICalendar\TimeZones;
use Phil\CalendarImport\SourceEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ICalendarReader::class)]
#[CoversClass(EventReader::class)]
#[CoversClass(ComponentTree::class)]
#[CoversClass(Component::class)]
#[CoversClass(ContentLines::class)]
#[CoversClass(ContentLine::class)]
#[CoversClass(TimeZones::class)]
#[CoversClass(Text::class)]
#[CoversClass(ReadResult::class)]
#[CoversClass(RejectedEvent::class)]
#[CoversClass(EventRefused::class)]
#[CoversClass(LimitExceeded::class)]
#[CoversClass(InvalidCalendar::class)]
#[CoversClass(Limit::class)]
final class ICalendarReaderTest extends TestCase
{
    /**
     * A calendar of the lines given, each event's lines between BEGIN:VEVENT and END:VEVENT.
     */
    private static function calendar(string ...$lines): string
    {
        return implode("\r\n", ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Test//EN', ...$lines, 'END:VCALENDAR'])."\r\n";
    }

    private static function event(string ...$lines): string
    {
        return implode("\r\n", ['BEGIN:VEVENT', ...$lines, 'END:VEVENT']);
    }

    private function read(string $text, ?DateTimeZone $zone = null, ReaderLimits $limits = new ReaderLimits()): ReadResult
    {
        return new ICalendarReader($limits)->read($text, $zone);
    }

    /**
     * @param list<SourceEvent> $events
     */
    private function find(array $events, string $key): SourceEvent
    {
        foreach ($events as $event) {
            if ($event->key() === $key) {
                return $event;
            }
        }

        self::fail(\sprintf('No event %s.', $key));
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/../../Fixtures/'.$name);
    }

    public function testACommunityCalendarIsReadWithItsZonesDaysAndRecurrences(): void
    {
        $result = $this->read($this->fixture('community-calendar.ics'), new DateTimeZone('Europe/Paris'));

        self::assertSame('Community events, autumn', $result->name);
        self::assertSame([], $result->rejected);
        self::assertSame(['board-games-1110@example.org', 'craft-fair-2026@example.org', 'weekly-club@example.org', 'weekly-club@example.org#20261124T230000Z', 'cancelled-talk@example.org', 'floating-brunch@example.org'], array_map(static fn (SourceEvent $event): string => $event->key(), $result->events));

        $games = $this->find($result->events, 'board-games-1110@example.org');
        self::assertSame('2026-11-10T19:00:00-05:00', $games->start->format(\DATE_ATOM));
        self::assertSame('America/Toronto', $games->timezone->getName());
        self::assertSame('2026-11-10T22:00:00-05:00', $games->end->format(\DATE_ATOM));
        self::assertFalse($games->allDay);
        self::assertFalse($games->floating);
        self::assertSame(2, $games->sequence);
        self::assertSame('2026-10-31T09:00:00+00:00', $games->lastModified?->format(\DATE_ATOM));
        self::assertSame('2026-11-01T12:00:00+00:00', $games->stamp?->format(\DATE_ATOM));
        self::assertSame('Board games night', $games->title);
        self::assertSame("Bring a game, or borrow one.\nDoors open at 18:30; snacks on site. <b>not bold</b>", $games->description);
        self::assertSame('Main hall, 12 Example Street', $games->location);
        self::assertSame('https://example.org/events/board-games', $games->url?->toString());
        self::assertSame(['games', 'evening', 'Games'], $games->tags);
        self::assertSame(EventStatus::Confirmed, $games->status);
        self::assertNull($games->rule);
        self::assertNull($games->recurrenceId);

        $fair = $this->find($result->events, 'craft-fair-2026@example.org');
        self::assertTrue($fair->allDay);
        self::assertFalse($fair->floating);
        self::assertSame('2026-11-21T00:00:00-05:00', $fair->start->format(\DATE_ATOM));
        self::assertSame('2026-11-23T00:00:00-05:00', $fair->end->format(\DATE_ATOM));
        self::assertSame(0, $fair->sequence);
        self::assertNull($fair->lastModified);

        $club = $this->find($result->events, 'weekly-club@example.org');
        self::assertSame('FREQ=WEEKLY;COUNT=6;BYDAY=TU', (string) $club->rule);
        self::assertSame('2026-11-03T20:00:00-05:00', $club->end->format(\DATE_ATOM));
        self::assertSame(['2026-11-17T18:00:00-05:00'], array_map(static fn (DateTimeImmutable $date): string => $date->format(\DATE_ATOM), $club->exclusions));
        self::assertSame(['2026-12-16T18:00:00-05:00'], array_map(static fn (DateTimeImmutable $date): string => $date->format(\DATE_ATOM), $club->additions));

        $late = $this->find($result->events, 'weekly-club@example.org#20261124T230000Z');
        self::assertSame('2026-11-24T18:00:00-05:00', $late->recurrenceId?->format(\DATE_ATOM));
        self::assertSame('Weekly club (late start)', $late->title);

        self::assertSame(EventStatus::Cancelled, $this->find($result->events, 'cancelled-talk@example.org')->status);

        $brunch = $this->find($result->events, 'floating-brunch@example.org');
        self::assertTrue($brunch->floating);
        self::assertSame('2026-12-12T10:00:00-05:00', $brunch->start->format(\DATE_ATOM), 'Floating times are read in the zone the calendar names.');
    }

    public function testFloatingTimesAndDaysAreReadInTheZoneGivenWhenTheCalendarNamesNone(): void
    {
        $text = self::calendar(
            self::event('UID:a', 'DTSTART:20261212T100000', 'DTEND:20261212T110000'),
            self::event('UID:b', 'DTSTART;VALUE=DATE:20261212'),
            self::event('UID:c', 'DTSTART:20261213'),
        );

        $result = $this->read($text, new DateTimeZone('Asia/Tokyo'));

        self::assertSame('2026-12-12T10:00:00+09:00', $result->events[0]->start->format(\DATE_ATOM));
        self::assertSame('Asia/Tokyo', $result->events[0]->timezone->getName());
        self::assertSame('2026-12-12T00:00:00+09:00', $result->events[1]->start->format(\DATE_ATOM));
        self::assertSame('2026-12-13T00:00:00+09:00', $result->events[1]->end->format(\DATE_ATOM), 'A day without an end lasts the day.');
        self::assertTrue($result->events[2]->allDay, 'Eight digits without VALUE=DATE are a day.');
        self::assertSame('UTC', $this->read($text)->events[0]->timezone->getName(), 'UTC without a zone given.');
    }

    public function testAZoneTheCalendarNamesThatPhpDoesNotKnowLeavesTheZoneGiven(): void
    {
        $text = self::calendar('X-WR-TIMEZONE:Mars/Olympus', self::event('UID:a', 'DTSTART:20261212T100000'));

        self::assertSame('Asia/Tokyo', $this->read($text, new DateTimeZone('Asia/Tokyo'))->events[0]->timezone->getName());
    }

    public function testATimeInUtcIgnoresItsTzidAndAnInstantEndsWhenItStarts(): void
    {
        $event = $this->read(self::calendar(self::event('UID:a', 'DTSTART;TZID=Europe/Paris:20261212T100000Z')))->events[0];

        self::assertSame('2026-12-12T10:00:00+00:00', $event->start->format(\DATE_ATOM));
        self::assertSame('UTC', $event->timezone->getName());
        self::assertSame($event->start->format(\DATE_ATOM), $event->end->format(\DATE_ATOM));
        self::assertFalse($event->floating);
    }

    public function testZonesAreResolvedByWindowsNamesLocationsPathsAndFixedOffsets(): void
    {
        $result = $this->read($this->fixture('windows-zones.ics'));

        self::assertSame('America/New_York', $this->find($result->events, '040000008200E00074C5B7101A82E0080000000010B2DCE3')->timezone->getName());
        self::assertSame('Europe/Paris', $this->find($result->events, 'club-meeting')->timezone->getName());
        self::assertSame('Europe/Paris', $this->find($result->events, 'mozilla-style')->timezone->getName());
        $call = $this->find($result->events, 'kolkata-call');
        self::assertSame('+05:30', $call->timezone->getName());
        self::assertSame('2026-11-10T09:00:00+05:30', $call->start->format(\DATE_ATOM));

        self::assertCount(1, $result->rejected);
        self::assertSame('made-up', $result->rejected[0]->uid);
        self::assertSame('made-up', $result->rejected[0]->key);
        self::assertSame(Rejection::UnknownTimeZone, $result->rejected[0]->reason);
        self::assertSame('The time zone "Made-up summer time" is neither known nor defined simply in the calendar.', $result->rejected[0]->message);
    }

    public function testAZoneIsKnownInAnyCaseAndWithTheSlashOfAUniqueName(): void
    {
        $result = $this->read(self::calendar(
            self::event('UID:a', 'DTSTART;TZID=europe/paris:20261212T100000'),
            self::event('UID:b', 'DTSTART;TZID=/Europe/Paris:20261212T100000'),
            self::event('UID:c', 'DTSTART;TZID="Europe/Paris":20261212T100000'),
        ));

        self::assertSame(['Europe/Paris', 'Europe/Paris', 'Europe/Paris'], array_map(static fn (SourceEvent $event): string => $event->timezone->getName(), $result->events));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function timeZoneDefinitions(): iterable
    {
        yield 'two offsets' => ["BEGIN:STANDARD\r\nTZOFFSETTO:+0100\r\nEND:STANDARD\r\nBEGIN:DAYLIGHT\r\nTZOFFSETTO:+0200\r\nEND:DAYLIGHT", 'Odd'];
        yield 'no offset' => ["BEGIN:STANDARD\r\nTZOFFSETFROM:+0100\r\nEND:STANDARD", 'Odd'];
        yield 'an offset with seconds' => ["BEGIN:STANDARD\r\nTZOFFSETTO:+013015\r\nEND:STANDARD", 'Odd'];
        yield 'hours past 18' => ["BEGIN:STANDARD\r\nTZOFFSETTO:+1900\r\nEND:STANDARD", 'Odd'];
        yield 'minutes past 59' => ["BEGIN:STANDARD\r\nTZOFFSETTO:+0160\r\nEND:STANDARD", 'Odd'];
        yield 'an offset without sign' => ["BEGIN:STANDARD\r\nTZOFFSETTO:0100\r\nEND:STANDARD", 'Odd'];
        yield 'an offset with text before' => ["BEGIN:STANDARD\r\nTZOFFSETTO:x+0100\r\nEND:STANDARD", 'Odd'];
        yield 'an offset with text after' => ["BEGIN:STANDARD\r\nTZOFFSETTO:+0100x\r\nEND:STANDARD", 'Odd'];
        yield 'a location PHP does not know' => ["X-LIC-LOCATION:Mars/Olympus\r\nBEGIN:STANDARD\r\nTZOFFSETTO:+0100\r\nEND:STANDARD\r\nBEGIN:DAYLIGHT\r\nTZOFFSETTO:+0200\r\nEND:DAYLIGHT", 'Odd'];
        yield 'a definition of a line that cannot be read' => ["BEGIN:STANDARD\r\nTZOFFSETTO:+0100\r\nbroken line\r\nEND:STANDARD", 'Odd'];
    }

    #[DataProvider('timeZoneDefinitions')]
    public function testAVtimezoneThatIsNotSimpleLeavesTheZoneUnknown(string $observances, string $tzid): void
    {
        $result = $this->read(self::calendar("BEGIN:VTIMEZONE\r\nTZID:".$tzid."\r\n".$observances."\r\nEND:VTIMEZONE", self::event('UID:a', 'DTSTART;TZID='.$tzid.':20261212T100000')));

        self::assertSame([], $result->events);
        self::assertSame(Rejection::UnknownTimeZone, $result->rejected[0]->reason);
    }

    public function testAFixedOffsetIsReadWhenEveryObservanceHasIt(): void
    {
        $result = $this->read(self::calendar(
            "BEGIN:VTIMEZONE\r\nTZID:West\r\nBEGIN:STANDARD\r\nTZOFFSETTO:-0330\r\nEND:STANDARD\r\nBEGIN:DAYLIGHT\r\nTZOFFSETTO:-0330\r\nEND:DAYLIGHT\r\nEND:VTIMEZONE",
            "BEGIN:VTIMEZONE\r\nTZID:Far\r\nBEGIN:STANDARD\r\nTZOFFSETTO:+184500\r\nEND:STANDARD\r\nEND:VTIMEZONE",
            "BEGIN:VTIMEZONE\r\nTZID:West\r\nBEGIN:STANDARD\r\nTZOFFSETTO:+0100\r\nEND:STANDARD\r\nEND:VTIMEZONE",
            self::event('UID:a', 'DTSTART;TZID=West:20261212T100000'),
            self::event('UID:b', 'DTSTART;TZID=Far:20261212T100000'),
        ));

        self::assertSame('-03:30', $result->events[0]->timezone->getName(), 'The first definition of a TZID is kept.');
        self::assertSame('+18:45', $result->events[1]->timezone->getName());
    }

    public function testATzidLongerThanAZoneNameIsUnknown(): void
    {
        $tzid = 'Europe/'.str_repeat('a', 249);
        $result = $this->read(self::calendar("BEGIN:VTIMEZONE\r\nTZID:".$tzid."\r\nX-LIC-LOCATION:Europe/Paris\r\nEND:VTIMEZONE", self::event('UID:a', 'DTSTART;TZID='.$tzid.':20261212T100000')));
        self::assertSame(Rejection::UnknownTimeZone, $result->rejected[0]->reason);
        self::assertSame(\sprintf('The time zone "%s" is neither known nor defined simply in the calendar.', substr($tzid, 0, 64)), $result->rejected[0]->message);

        $tzid = 'Europe/'.str_repeat('a', 248);
        self::assertSame('Europe/Paris', $this->read(self::calendar("BEGIN:VTIMEZONE\r\nTZID:".$tzid."\r\nX-LIC-LOCATION:Europe/Paris\r\nEND:VTIMEZONE", self::event('UID:a', 'DTSTART;TZID='.$tzid.':20261212T100000')))->events[0]->timezone->getName());
    }

    public function testDurationsEndAnEventOnTheWallClock(): void
    {
        $result = $this->read(self::calendar(
            self::event('UID:a', 'DTSTART;TZID=America/Toronto:20261031T120000', 'DURATION:P1DT2H30M15S'),
            self::event('UID:b', 'DTSTART;VALUE=DATE:20261031', 'DURATION:P2W'),
            self::event('UID:c', 'DTSTART;VALUE=DATE:20261031', 'DURATION:+P3D'),
            self::event('UID:d', 'DTSTART:20261031T120000Z', 'DURATION:PT0S'),
            self::event('UID:e', 'DTSTART:20261031T120000Z', 'DURATION: PT1M '),
        ));

        self::assertSame('2026-11-01T14:30:15-05:00', $result->events[0]->end->format(\DATE_ATOM), 'A day across a change of time is a day on the clock.');
        self::assertSame('2026-11-14T00:00:00+00:00', $result->events[1]->end->format(\DATE_ATOM));
        self::assertSame('2026-11-03T00:00:00+00:00', $result->events[2]->end->format(\DATE_ATOM));
        self::assertSame('2026-10-31T12:00:00+00:00', $result->events[3]->end->format(\DATE_ATOM));
        self::assertSame('2026-10-31T12:01:00+00:00', $result->events[4]->end->format(\DATE_ATOM));
    }

    /**
     * @return iterable<string, array{list<string>, Rejection, string}>
     */
    public static function refusedEvents(): iterable
    {
        yield 'no UID' => [['DTSTART:20261212T100000Z'], Rejection::MissingUid, 'An event has a UID.'];
        yield 'an empty UID' => [['UID: ', 'DTSTART:20261212T100000Z'], Rejection::MissingUid, 'An event has a UID.'];
        yield 'a UID too long' => [['UID:'.str_repeat('u', 256), 'DTSTART:20261212T100000Z'], Rejection::InvalidValue, 'A UID is 255 bytes at most, on one line.'];
        yield 'a UID on two lines' => [['UID:a\nb', 'DTSTART:20261212T100000Z'], Rejection::InvalidValue, 'A UID is 255 bytes at most, on one line.'];
        yield 'a UID with a tab' => [["UID:a\tb", 'DTSTART:20261212T100000Z'], Rejection::InvalidValue, 'A UID is 255 bytes at most, on one line.'];
        yield 'no UID, private' => [['CLASS:PRIVATE', 'DTSTART:20261212T100000Z'], Rejection::MissingUid, 'An event has a UID.'];
        yield 'private' => [['UID:a', 'CLASS:private', 'DTSTART:20261212T100000Z'], Rejection::Private, 'The event is marked PRIVATE.'];
        yield 'confidential' => [['UID:a', 'CLASS: CONFIDENTIAL ', 'DTSTART:20261212T100000Z'], Rejection::Private, 'The event is marked CONFIDENTIAL.'];
        yield 'no start' => [['UID:a', 'CLASS:PUBLIC'], Rejection::MissingStart, 'An event has a start (DTSTART).'];
        yield 'two starts' => [['UID:a', 'DTSTART:20261212T100000Z', 'DTSTART:20261212T110000Z'], Rejection::Malformed, 'An event holds one DTSTART at most.'];
        yield 'two UIDs' => [['UID:a', 'UID:b', 'DTSTART:20261212T100000Z'], Rejection::Malformed, 'An event holds one UID at most.'];
        yield 'two rules' => [['UID:a', 'DTSTART:20261212T100000Z', 'RRULE:FREQ=DAILY', 'RRULE:FREQ=WEEKLY'], Rejection::Malformed, 'An event holds one RRULE at most.'];
        yield 'two changed occurrences' => [['UID:a', 'DTSTART:20261212T100000Z', 'RECURRENCE-ID:20261212T100000Z', 'RECURRENCE-ID:20261213T100000Z'], Rejection::Malformed, 'An event holds one RECURRENCE-ID at most.'];
        yield 'a line that cannot be read' => [['UID:a', 'DTSTART:20261212T100000Z', 'SUMMARY'], Rejection::Malformed, '"SUMMARY" is not a name, parameters, a colon and a value.'];
        yield 'a line of an alarm that cannot be read' => [['UID:a', 'DTSTART:20261212T100000Z', 'BEGIN:VALARM', 'TRIGGER;=x:-PT1M', 'END:VALARM'], Rejection::Malformed, '"TRIGGER;=x:-PT1M" is not a name, parameters, a colon and a value.'];
        yield 'a parameter twice' => [['UID:a', 'dtstart;TZID=UTC;tzid=UTC:20261212T100000'], Rejection::Malformed, 'The parameter TZID of DTSTART appears twice.'];
        yield 'two lines that cannot be read' => [['UID:a', 'DTSTART:20261212T100000Z', 'SUMMARY', 'LOCATION'], Rejection::Malformed, '"SUMMARY" is not a name, parameters, a colon and a value.'];
        yield 'a time with text before' => [['UID:a', 'DTSTART:x20261212T100000Z'], Rejection::InvalidDate, 'The DTSTART of an event is written 20261110, or 20261110T190000 with a Z or not.'];
        yield 'a start of letters' => [['UID:a', 'DTSTART:tomorrow'], Rejection::InvalidDate, 'The DTSTART of an event is written 20261110, or 20261110T190000 with a Z or not.'];
        yield 'a start of another type' => [['UID:a', 'DTSTART;VALUE=PERIOD:20261212T100000Z'], Rejection::InvalidDate, 'The DTSTART of an event is written 20261110, or 20261110T190000 with a Z or not.'];
        yield 'a day as a date-time' => [['UID:a', 'DTSTART;VALUE=DATE-TIME:20261212'], Rejection::InvalidDate, 'The DTSTART of an event is written 20261110, or 20261110T190000 with a Z or not.'];
        yield 'a time as a day' => [['UID:a', 'DTSTART;VALUE=DATE:20261212T100000'], Rejection::InvalidDate, 'The DTSTART of an event is written 20261110, or 20261110T190000 with a Z or not.'];
        yield 'a start with text after' => [['UID:a', 'DTSTART:20261212T100000Zx'], Rejection::InvalidDate, 'The DTSTART of an event is written 20261110, or 20261110T190000 with a Z or not.'];
        yield 'a day with text before' => [['UID:a', 'DTSTART:x20261212'], Rejection::InvalidDate, 'The DTSTART of an event is written 20261110, or 20261110T190000 with a Z or not.'];
        yield 'a day with text after' => [['UID:a', 'DTSTART:20261212x'], Rejection::InvalidDate, 'The DTSTART of an event is written 20261110, or 20261110T190000 with a Z or not.'];
        yield 'a day that does not exist' => [['UID:a', 'DTSTART;VALUE=DATE:20260230'], Rejection::InvalidDate, 'The DTSTART of an event is a day that exists, in the years 1 to 9999.'];
        yield 'an hour that does not exist' => [['UID:a', 'DTSTART:20261212T250000Z'], Rejection::InvalidDate, 'The DTSTART of an event is a day that exists, in the years 1 to 9999.'];
        yield 'the year 0' => [['UID:a', 'DTSTART:00000101T000000Z'], Rejection::InvalidDate, 'The DTSTART of an event is a day that exists, in the years 1 to 9999.'];
        yield 'an end that is a day' => [['UID:a', 'DTSTART:20261212T100000Z', 'DTEND;VALUE=DATE:20261213'], Rejection::InvalidDate, 'The end of an event is a day when its start is, a time when its start is.'];
        yield 'an end that is a time' => [['UID:a', 'DTSTART;VALUE=DATE:20261212', 'DTEND:20261213T100000Z'], Rejection::InvalidDate, 'The end of an event is a day when its start is, a time when its start is.'];
        yield 'an end before the start' => [['UID:a', 'DTSTART:20261212T100000Z', 'DTEND:20261212T095959Z'], Rejection::InvalidDate, 'An event ends after it starts.'];
        yield 'an end and a duration' => [['UID:a', 'DTSTART:20261212T100000Z', 'DTEND:20261212T110000Z', 'DURATION:PT1H'], Rejection::Malformed, 'An event ends with DTEND or lasts a DURATION, not both.'];
        yield 'a negative duration' => [['UID:a', 'DTSTART:20261212T100000Z', 'DURATION:-PT1H'], Rejection::InvalidDuration, 'A duration is written P1W, P1D, PT2H30M or P1DT2H, not negative.'];
        yield 'an empty duration' => [['UID:a', 'DTSTART:20261212T100000Z', 'DURATION:P'], Rejection::InvalidDuration, 'A duration is written P1W, P1D, PT2H30M or P1DT2H, not negative.'];
        yield 'a duration with an empty time' => [['UID:a', 'DTSTART:20261212T100000Z', 'DURATION:P1DT'], Rejection::InvalidDuration, 'A duration is written P1W, P1D, PT2H30M or P1DT2H, not negative.'];
        yield 'a duration with text after' => [['UID:a', 'DTSTART:20261212T100000Z', 'DURATION:PT1Hx'], Rejection::InvalidDuration, 'A duration is written P1W, P1D, PT2H30M or P1DT2H, not negative.'];
        yield 'a duration with text before' => [['UID:a', 'DTSTART:20261212T100000Z', 'DURATION:xPT1H'], Rejection::InvalidDuration, 'A duration is written P1W, P1D, PT2H30M or P1DT2H, not negative.'];
        yield 'hours for a day' => [['UID:a', 'DTSTART;VALUE=DATE:20261212', 'DURATION:P1DT1H'], Rejection::InvalidDuration, 'An event of whole days lasts whole days or weeks.'];
        yield 'a sequence of letters' => [['UID:a', 'DTSTART:20261212T100000Z', 'SEQUENCE:two'], Rejection::InvalidValue, 'The sequence of an event is a whole number, nine digits at most.'];
        yield 'a sequence too large' => [['UID:a', 'DTSTART:20261212T100000Z', 'SEQUENCE:1234567890'], Rejection::InvalidValue, 'The sequence of an event is a whole number, nine digits at most.'];
        yield 'a negative sequence' => [['UID:a', 'DTSTART:20261212T100000Z', 'SEQUENCE:-1'], Rejection::InvalidValue, 'The sequence of an event is a whole number, nine digits at most.'];
        yield 'a sequence with text before' => [['UID:a', 'DTSTART:20261212T100000Z', 'SEQUENCE:x1'], Rejection::InvalidValue, 'The sequence of an event is a whole number, nine digits at most.'];
        yield 'a sequence with text after' => [['UID:a', 'DTSTART:20261212T100000Z', 'SEQUENCE:1x'], Rejection::InvalidValue, 'The sequence of an event is a whole number, nine digits at most.'];
        yield 'a last change not in UTC' => [['UID:a', 'DTSTART:20261212T100000Z', 'LAST-MODIFIED:20261212T100000'], Rejection::InvalidDate, 'The LAST-MODIFIED of an event is a time in UTC, written 20261110T190000Z.'];
        yield 'a stamp with text after' => [['UID:a', 'DTSTART:20261212T100000Z', 'DTSTAMP:20261212T100000Zx'], Rejection::InvalidDate, 'The DTSTAMP of an event is a time in UTC, written 20261110T190000Z.'];
        yield 'a stamp with text before' => [['UID:a', 'DTSTART:20261212T100000Z', 'DTSTAMP:x20261212T100000Z'], Rejection::InvalidDate, 'The DTSTAMP of an event is a time in UTC, written 20261110T190000Z.'];
        yield 'a stamp that does not exist' => [['UID:a', 'DTSTART:20261212T100000Z', 'DTSTAMP:20261312T100000Z'], Rejection::InvalidDate, 'The DTSTAMP of an event is a day that exists, in the years 1 to 9999.'];
        yield 'an unknown status' => [['UID:a', 'DTSTART:20261212T100000Z', 'STATUS:DONE'], Rejection::InvalidValue, 'The status of an event is TENTATIVE, CONFIRMED or CANCELLED.'];
        yield 'a rule phil/recurrence does not read' => [['UID:a', 'DTSTART:20261212T100000Z', 'RRULE:FREQ=HOURLY'], Rejection::InvalidRecurrence, 'is not a recurrence rule with a yearly, monthly, weekly or daily frequency.'];
        yield 'periods added' => [['UID:a', 'DTSTART:20261212T100000Z', 'RDATE;VALUE=PERIOD:20261213T100000Z/PT1H'], Rejection::InvalidRecurrence, 'Periods of a line of dates are not read.'];
        yield 'a kind of dates that cannot be written' => [['UID:a', 'DTSTART:20261212T100000Z', 'EXDATE;VALUE="DATE:1":20261213'], Rejection::InvalidRecurrence, 'The kind of the dates of EXDATE is DATE or DATE-TIME.'];
        yield 'a kind of dates on two lines' => [['UID:a', 'DTSTART:20261212T100000Z', 'EXDATE;VALUE=DATE^n:20261213'], Rejection::InvalidRecurrence, 'The kind of the dates of EXDATE is DATE or DATE-TIME.'];
        yield 'a kind of dates too long' => [['UID:a', 'DTSTART:20261212T100000Z', 'EXDATE;VALUE=ABCDEFGHIJKLMNOPQRSTU:20261213'], Rejection::InvalidRecurrence, 'The kind of the dates of EXDATE is DATE or DATE-TIME.'];
        yield 'a removed date in an unknown zone' => [['UID:a', 'DTSTART:20261212T100000Z', 'EXDATE;TZID=Nowhere:20261213T100000'], Rejection::UnknownTimeZone, 'The time zone "Nowhere" is neither known nor defined simply in the calendar.'];
        yield 'too many dates' => [['UID:a', 'DTSTART:20261212T100000Z', 'EXDATE:'.implode(',', array_fill(0, 600, '20261213T100000Z')), 'EXDATE:'.implode(',', array_fill(0, 401, '20261214T100000Z'))], Rejection::InvalidRecurrence, 'An event adds or removes 1000 dates at most.'];
        yield 'a change of the future' => [['UID:a', 'DTSTART:20261212T100000Z', 'RECURRENCE-ID;RANGE=THISANDFUTURE:20261212T100000Z'], Rejection::Unsupported, 'A change of this and the future occurrences (RANGE) is not read.'];
        yield 'too many categories' => [['UID:a', 'DTSTART:20261212T100000Z', 'CATEGORIES:'.implode(',', array_map(static fn (int $i): string => 't'.$i, range(1, 60))), 'CATEGORIES:'.implode(',', array_map(static fn (int $i): string => 't'.$i, range(41, 101)))], Rejection::InvalidValue, 'An event has 100 categories at most.'];
    }

    /**
     * @param list<string> $lines
     */
    #[DataProvider('refusedEvents')]
    public function testAnEventIsRefusedAloneWithItsReason(array $lines, Rejection $reason, string $message): void
    {
        $result = $this->read(self::calendar(self::event(...$lines), self::event('UID:kept', 'DTSTART:20261212T100000Z')));

        self::assertSame(['kept'], array_map(static fn (SourceEvent $event): string => $event->uid, $result->events));
        self::assertCount(1, $result->rejected);
        self::assertSame($reason, $result->rejected[0]->reason);
        self::assertStringContainsString($message, $result->rejected[0]->message);
    }

    public function testAnEventAtTheBoundsIsRead(): void
    {
        $result = $this->read(self::calendar(self::event(
            'UID:'.str_repeat('u', 255),
            'DTSTART:00010101T000000Z',
            'DTEND:00010101T000000Z',
            'SEQUENCE:999999999',
            'EXDATE:'.implode(',', array_fill(0, 600, '20261213T100000Z')),
            'EXDATE:'.implode(',', array_fill(0, 400, '20261214T100000Z')),
            'CATEGORIES:'.implode(',', array_map(static fn (int $i): string => 't'.$i, range(1, 100))),
            'CATEGORIES:t1',
        )));

        self::assertSame([], $result->rejected);
        self::assertSame(999_999_999, $result->events[0]->sequence);
        self::assertCount(1000, $result->events[0]->exclusions);
        self::assertCount(100, $result->events[0]->tags);
    }

    public function testTheKeyOfARefusedEventNamesItsOccurrence(): void
    {
        $result = $this->read(self::calendar(
            self::event('UID:a', 'RECURRENCE-ID;VALUE=DATE:20261213', 'DTSTART:bad'),
            self::event('UID:b', 'RECURRENCE-ID:20261213T100000Z', 'DTSTART:bad'),
            self::event('UID:c', 'RECURRENCE-ID:bad', 'DTSTART:bad'),
            self::event('DTSTART:bad'),
        ));

        self::assertSame(['a#20261213', 'b#20261213T100000Z', 'c', null], array_map(static fn (RejectedEvent $rejected): ?string => $rejected->key, $result->rejected));
        self::assertSame(['a', 'b', 'c', null], array_map(static fn (RejectedEvent $rejected): ?string => $rejected->uid, $result->rejected));
    }

    public function testAnAddressPhilUrlRefusesIsLeftOutAndTheEventKept(): void
    {
        $result = $this->read(self::calendar(
            self::event('UID:a', 'DTSTART:20261212T100000Z', 'URL:javascript:alert(1)'),
            self::event('UID:b', 'DTSTART:20261212T100000Z', 'URL;VALUE=URI: https://example.org/b '),
        ));

        self::assertNull($result->events[0]->url);
        self::assertSame('https://example.org/b', $result->events[1]->url?->toString());
    }

    public function testStatusesAreReadInAnyCase(): void
    {
        $result = $this->read(self::calendar(
            self::event('UID:a', 'DTSTART:20261212T100000Z', 'STATUS:tentative'),
            self::event('UID:b', 'DTSTART:20261212T100000Z', 'STATUS: CANCELLED '),
            self::event('UID:c', 'DTSTART:20261212T100000Z', 'CLASS:PUBLIC'),
        ));

        self::assertSame([EventStatus::Tentative, EventStatus::Cancelled, EventStatus::Confirmed], array_map(static fn (SourceEvent $event): EventStatus => $event->status, $result->events));
    }

    public function testDatesAddedOrRemovedAreReadAsDaysInUtcOrInTheirZone(): void
    {
        $event = $this->read(self::calendar(self::event(
            'UID:a',
            'DTSTART;VALUE=DATE:20261212',
            'RRULE:FREQ=DAILY;UNTIL=20261231',
            'EXDATE;VALUE=DATE:20261214,20261215',
            'RDATE:20270101T100000Z',
            'RDATE;TZID=Asia/Tokyo:20270102T100000',
            'EXDATE:20261216T100000',
        )), new DateTimeZone('Europe/Paris'))->events[0];

        self::assertSame(['2026-12-14T00:00:00+01:00', '2026-12-15T00:00:00+01:00', '2026-12-16T10:00:00+01:00'], array_map(static fn (DateTimeImmutable $date): string => $date->format(\DATE_ATOM), $event->exclusions));
        self::assertSame(['2027-01-01T10:00:00+00:00', '2027-01-02T10:00:00+09:00'], array_map(static fn (DateTimeImmutable $date): string => $date->format(\DATE_ATOM), $event->additions));
        self::assertSame('FREQ=DAILY;UNTIL=20261231', (string) $event->rule);
    }

    public function testTheNewestOfTwoEventsOfTheSameKeyIsKept(): void
    {
        $result = $this->read(self::calendar(
            self::event('UID:seq', 'SUMMARY:old', 'SEQUENCE:1', 'DTSTART:20261212T100000Z'),
            self::event('UID:seq', 'SUMMARY:new', 'SEQUENCE:2', 'DTSTART:20261212T100000Z'),
            self::event('UID:seq', 'SUMMARY:older', 'SEQUENCE:0', 'DTSTART:20261212T100000Z'),
            self::event('UID:mod', 'SUMMARY:new', 'LAST-MODIFIED:20261102T000000Z', 'DTSTART:20261212T100000Z'),
            self::event('UID:mod', 'SUMMARY:old', 'LAST-MODIFIED:20261101T000000Z', 'DTSTAMP:20261201T000000Z', 'DTSTART:20261212T100000Z'),
            self::event('UID:mod2', 'SUMMARY:old', 'LAST-MODIFIED:20261101T000000Z', 'DTSTART:20261212T100000Z'),
            self::event('UID:mod2', 'SUMMARY:new', 'LAST-MODIFIED:20261102T000000Z', 'DTSTART:20261212T100000Z'),
            self::event('UID:stamp', 'SUMMARY:old', 'LAST-MODIFIED:20261101T000000Z', 'DTSTAMP:20261101T000000Z', 'DTSTART:20261212T100000Z'),
            self::event('UID:stamp', 'SUMMARY:new', 'LAST-MODIFIED:20261101T000000Z', 'DTSTAMP:20261102T000000Z', 'DTSTART:20261212T100000Z'),
            self::event('UID:half', 'SUMMARY:first', 'LAST-MODIFIED:20261101T000000Z', 'DTSTART:20261212T100000Z'),
            self::event('UID:half', 'SUMMARY:second', 'DTSTAMP:20261102T000000Z', 'DTSTART:20261212T100000Z'),
            self::event('UID:same', 'SUMMARY:first', 'DTSTART:20261212T100000Z'),
            self::event('UID:same', 'SUMMARY:second', 'DTSTART:20261212T100000Z'),
            self::event('UID:same', 'RECURRENCE-ID:20261212T100000Z', 'SUMMARY:occurrence', 'DTSTART:20261212T110000Z'),
        ));

        $titles = [];

        foreach ($result->events as $event) {
            $titles[$event->key()] = $event->title;
        }

        self::assertSame(['seq' => 'new', 'mod' => 'new', 'mod2' => 'new', 'stamp' => 'new', 'half' => 'first', 'same' => 'first', 'same#20261212T100000Z' => 'occurrence'], $titles);
        self::assertCount(7, $result->rejected);
        self::assertSame(Rejection::DuplicateUid, $result->rejected[0]->reason);
        self::assertSame('seq', $result->rejected[0]->key);
        self::assertSame('Another event has the UID "seq" and the same occurrence; the newest is kept.', $result->rejected[0]->message);
    }

    public function testEventsOfSeveralCalendarsAreReadTogetherWithTheFirstName(): void
    {
        $text = self::calendar('X-WR-CALNAME:First', self::event('UID:a', 'DTSTART:20261212T100000'))
            .self::calendar('X-WR-CALNAME:Second', 'X-WR-TIMEZONE:Asia/Tokyo', self::event('UID:b', 'DTSTART:20261212T100000'));

        $result = $this->read($text);

        self::assertSame('First', $result->name);
        self::assertSame(['UTC', 'Asia/Tokyo'], array_map(static fn (SourceEvent $event): string => $event->timezone->getName(), $result->events));
        self::assertSame('Second', $this->read(self::calendar('X-WR-CALNAME: ', 'X-WR-CALNAME:Ignored').self::calendar('X-WR-CALNAME:Second'))->name);
        self::assertNull($this->read(self::calendar())->name);
    }

    public function testComponentsOtherThanEventsAreSkipped(): void
    {
        $result = $this->read(self::calendar(
            "BEGIN:VTODO\r\nUID:todo\r\nDTSTART:20261212T100000Z\r\nbroken line\r\nEND:VTODO",
            "BEGIN:VJOURNAL\r\nUID:journal\r\nEND:VJOURNAL",
            self::event('UID:a', 'DTSTART:20261212T100000Z', 'X-ALT-DESC;FMTTYPE=text/html:<p>html</p>', 'DESCRIPTION:plain'),
        ));

        self::assertCount(1, $result->events);
        self::assertSame('plain', $result->events[0]->description);
        self::assertSame([], $result->rejected);
    }

    public function testAByteOrderMarkLoneLineFeedsAndBlankLinesAreRead(): void
    {
        $text = "\u{FEFF}BEGIN:VCALENDAR\nBEGIN:VEVENT\nUID:a\n\nDTSTART:20261212T100000Z\nSUMMARY:fo\n\tlded\rEND:VEVENT\r\nEND:VCALENDAR";

        $event = $this->read($text)->events[0];

        self::assertSame('folded', $event->title);
    }

    public function testACharacterFoldedInTheMiddleOfItsBytesIsReadWhole(): void
    {
        $text = self::calendar(self::event('UID:a', 'DTSTART:20261212T100000Z', "SUMMARY:caf\xC3\r\n \xA9"));

        self::assertSame('café', $this->read($text)->events[0]->title);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidCalendars(): iterable
    {
        yield 'empty' => ['', 'A calendar starts with BEGIN:VCALENDAR.'];
        yield 'blank lines' => ["\r\n\r\n", 'A calendar starts with BEGIN:VCALENDAR.'];
        yield 'not iCalendar' => ["<html>\r\n", 'A calendar starts with BEGIN:VCALENDAR.'];
        yield 'another component first' => ["BEGIN:VEVENT\r\nEND:VEVENT\r\n", 'A calendar starts with BEGIN:VCALENDAR.'];
        yield 'a property first' => ["VERSION:2.0\r\nBEGIN:VCALENDAR\r\nEND:VCALENDAR", 'A calendar starts with BEGIN:VCALENDAR.'];
        yield 'not closed' => ["BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nEND:VEVENT", 'A calendar ends with END:VCALENDAR.'];
        yield 'something after' => ["BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\nUID:a", 'A calendar holds nothing after END:VCALENDAR.'];
        yield 'a line that cannot be read after' => ["BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\nbroken", 'A calendar holds nothing after END:VCALENDAR.'];
        yield 'ends out of order' => ["BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nEND:VCALENDAR\r\nEND:VEVENT", 'The components of a calendar end in the order they begin.'];
        yield 'an end of nothing' => ["BEGIN:VCALENDAR\r\nEND:VEVENT\r\nEND:VCALENDAR", 'The components of a calendar end in the order they begin.'];
        yield 'a component without a name' => ["BEGIN:VCALENDAR\r\nBEGIN:\r\nEND:VCALENDAR", 'A component of a calendar is named by letters, digits and hyphens.'];
        yield 'a component with a space' => ["BEGIN:VCALENDAR\r\nBEGIN:V EVENT\r\nEND:VCALENDAR", 'A component of a calendar is named by letters, digits and hyphens.'];
        yield 'a component name too long' => ["BEGIN:VCALENDAR\r\nBEGIN:".str_repeat('X', 65)."\r\nEND:VCALENDAR", 'A component of a calendar is named by letters, digits and hyphens.'];
        yield 'an end with a space' => ["BEGIN:VCALENDAR\r\nEND:VCALENDAR \r\n", 'A component of a calendar is named by letters, digits and hyphens.'];
        yield 'a calendar line that cannot be read' => ["BEGIN:VCALENDAR\r\nVERSION\r\nEND:VCALENDAR", '"VERSION" is not a name, parameters, a colon and a value.'];
        yield 'not UTF-8' => ["BEGIN:VCALENDAR\r\nX-NAME:caf\xE9\r\nEND:VCALENDAR", 'A calendar is written in UTF-8.'];
    }

    #[DataProvider('invalidCalendars')]
    public function testATextBuiltWronglyIsRefusedWhole(string $text, string $message): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->read($text);
    }

    public function testTheNameOfAComponentIsReadInAnyCase(): void
    {
        $result = $this->read("begin:vcalendar\r\nbegin:vevent\r\nuid:a\r\ndtstart:20261212T100000Z\r\nend:VEVENT\r\nEnd:VCalendar");

        self::assertSame('a', $result->events[0]->uid);
        self::assertSame('a', $this->read("BEGIN:VCALENDAR\r\nBEGIN:VEVENT-2\r\nEND:VEVENT-2\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTART:20261212T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR")->events[0]->uid);
    }

    /**
     * @return iterable<string, array{string, ReaderLimits, Limit, int}>
     */
    public static function bounds(): iterable
    {
        $two = self::calendar(self::event('UID:a', 'DTSTART:20261212T100000Z'), self::event('UID:b', 'DTSTART:20261212T100000Z'));

        yield 'bytes' => [$two, new ReaderLimits(bytes: \strlen($two) - 1), Limit::Bytes, \strlen($two) - 1];
        yield 'events' => [$two, new ReaderLimits(events: 1), Limit::Events, 1];
        yield 'components' => [$two, new ReaderLimits(components: 2), Limit::Components, 2];
        yield 'depth' => [self::calendar(self::event('UID:a', 'DTSTART:20261212T100000Z', 'BEGIN:VALARM', 'END:VALARM')), new ReaderLimits(depth: 2), Limit::Depth, 2];
        yield 'line' => [self::calendar(self::event('UID:a', 'DTSTART:20261212T100000Z', 'SUMMARY:'.str_repeat('x', 92))), new ReaderLimits(lineBytes: 99), Limit::LineBytes, 99];
        yield 'folded line' => [self::calendar(self::event('UID:a', 'DTSTART:20261212T100000Z', 'SUMMARY:'.str_repeat('x', 50)."\r\n ".str_repeat('x', 42))), new ReaderLimits(lineBytes: 99), Limit::LineBytes, 99];
        yield 'line that cannot be read' => [self::calendar(self::event('UID:a', 'DTSTART:20261212T100000Z', str_repeat('x', 100))), new ReaderLimits(lineBytes: 99), Limit::LineBytes, 99];
    }

    #[DataProvider('bounds')]
    public function testATextPastABoundIsRefusedWholeWithTheBoundNamed(string $text, ReaderLimits $limits, Limit $limit, int $max): void
    {
        try {
            $this->read($text, null, $limits);
            self::fail('The text is past a bound.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame($limit, $limitExceeded->limit);
            self::assertSame($max, $limitExceeded->max);
            self::assertSame(\sprintf('The calendar is past its limit: %s, %d at most.', $limit->describe(), $max), $limitExceeded->getMessage());
        }
    }

    public function testATextAtItsBoundsIsRead(): void
    {
        $two = self::calendar(self::event('UID:a', 'DTSTART:20261212T100000Z', 'SUMMARY:'.str_repeat('x', 91)), self::event('UID:b', 'DTSTART:20261212T100000Z', 'BEGIN:VALARM', 'END:VALARM'));

        $result = $this->read($two, null, new ReaderLimits(bytes: \strlen($two), events: 2, lineBytes: 99, depth: 3, components: 4));

        self::assertCount(2, $result->events);
    }

    public function testEachBoundIsDescribed(): void
    {
        self::assertSame(['bytes in the text', 'events', 'bytes in an unfolded line', 'components nested in one another', 'components'], array_map(static fn (Limit $limit): string => $limit->describe(), Limit::cases()));
    }

    public function testValuesAreReadWithoutTheSpacesAroundThem(): void
    {
        $event = $this->read(self::calendar(self::event(
            'UID:a',
            'DTSTART;VALUE=date:20261212 ',
            'SEQUENCE: 3 ',
            'DTSTAMP:20261101T120000Z ',
            'URL: https://example.org/a/b ',
        )))->events[0];

        self::assertTrue($event->allDay);
        self::assertSame('2026-12-12', $event->start->format('Y-m-d'));
        self::assertSame(3, $event->sequence);
        self::assertSame('2026-11-01T12:00:00+00:00', $event->stamp?->format(\DATE_ATOM));
        self::assertSame('https://example.org/a/b', $event->url?->toString());
    }

    public function testTheTagsOfSeveralLinesAreAListEachTagOnce(): void
    {
        $event = $this->read(self::calendar(self::event('UID:a', 'DTSTART:20261212T100000Z', 'CATEGORIES:a,b', 'CATEGORIES:a,c')))->events[0];

        self::assertSame(['a', 'b', 'c'], $event->tags);
    }

    public function testTheZoneOfTheCalendarIsReadWithoutSpacesNorTheSlashOfAUniqueName(): void
    {
        $result = $this->read(
            self::calendar('X-WR-TIMEZONE: Asia/Tokyo ', self::event('UID:a', 'DTSTART:20261212T100000'))
            .self::calendar('X-WR-TIMEZONE:/Europe/Paris', self::event('UID:b', 'DTSTART:20261212T100000')),
        );

        self::assertSame(['Asia/Tokyo', 'Europe/Paris'], array_map(static fn (SourceEvent $event): string => $event->timezone->getName(), $result->events));
    }

    public function testAZoneIsResolvedByItsIanaNameBeforeItsWindowsName(): void
    {
        $result = $this->read(self::calendar(
            self::event('UID:a', 'DTSTART;TZID=UTC:20261212T100000'),
            self::event('UID:b', 'DTSTART;TZID="Eastern Standard Time ":20261212T100000'),
            self::event('UID:c', 'DTSTART;TZID=Custom/America/Toronto:20261212T100000'),
        ));

        self::assertSame(['UTC', 'America/New_York', 'America/Toronto'], array_map(static fn (SourceEvent $event): string => $event->timezone->getName(), $result->events));
    }

    public function testALocationOfAVtimezoneComesBeforeItsOffset(): void
    {
        $result = $this->read(self::calendar(
            "BEGIN:VTIMEZONE\r\nTZID:Home\r\nX-LIC-LOCATION:America/Toronto\r\nBEGIN:STANDARD\r\nTZOFFSETTO:-0500\r\nEND:STANDARD\r\nEND:VTIMEZONE",
            self::event('UID:a', 'DTSTART;TZID=Home:20261212T100000'),
        ));

        self::assertSame('America/Toronto', $result->events[0]->timezone->getName());
    }

    public function testTheMessagesOfARefusalCutLongValuesAtACharacter(): void
    {
        $result = $this->read(self::calendar(self::event('UID:a', 'DTSTART;TZID='.str_repeat('z', 63).'é:20261212T100000')));
        self::assertSame(\sprintf('The time zone "%s" is neither known nor defined simply in the calendar.', str_repeat('z', 63)), $result->rejected[0]->message);

        $uid = str_repeat('u', 70);
        $result = $this->read(self::calendar(self::event('UID:'.$uid, 'DTSTART:20261212T100000Z'), self::event('UID:'.$uid, 'DTSTART:20261212T100000Z')));
        self::assertSame(\sprintf('Another event has the UID "%s" and the same occurrence; the newest is kept.', str_repeat('u', 64)), $result->rejected[0]->message);

        $uid = str_repeat('u', 63).'é';
        $result = $this->read(self::calendar(self::event('UID:'.$uid, 'DTSTART:20261212T100000Z'), self::event('UID:'.$uid, 'DTSTART:20261212T100000Z')));
        self::assertSame(\sprintf('Another event has the UID "%s" and the same occurrence; the newest is kept.', str_repeat('u', 63)), $result->rejected[0]->message);
    }

    public function testTheEventsOfAComponentAreAList(): void
    {
        $calendar = new Component('VCALENDAR');
        $calendar->children = [new Component('VTIMEZONE'), new Component('VEVENT'), new Component('VTODO'), new Component('VEVENT')];

        self::assertSame([$calendar->children[1], $calendar->children[3]], $calendar->childrenNamed('VEVENT'));
    }
}
