<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Tests\Unit\ICalendar;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\BusyPeriods;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\ICalendar\Components;
use Phil\CalendarSync\ICalendar\EventTimes;
use Phil\CalendarSync\ICalendar\ICalendarReader;
use Phil\CalendarSync\ICalendar\Periods;
use Phil\CalendarSync\ICalendar\RuleBudget;
use Phil\CalendarSync\SyncWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ICalendarReader::class)]
#[CoversClass(Periods::class)]
#[CoversClass(Components::class)]
#[CoversClass(EventTimes::class)]
#[CoversClass(RuleBudget::class)]
final class ICalendarReaderTest extends TestCase
{
    private const string TORONTO = 'America/Toronto';

    public function testAnEventGivesItsTimesAndNothingElse(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'UID:a@example.com',
            'DTSTART;TZID=America/Toronto:20261110T190000',
            'DTEND;TZID=America/Toronto:20261110T220000',
            'SUMMARY:Secret meeting',
            'DESCRIPTION:Do not leak',
            'END:VEVENT',
        ));

        self::assertSame([['2026-11-11T00:00:00Z', '2026-11-11T03:00:00Z', false]], $this->times($periods));
    }

    public function testTimesAreReadInUtcInTheirZoneOrInTheCalendarZone(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'DTSTART:20261110T120000Z',
            'DURATION:PT1H30M',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART:20261111T090000',
            'DTEND:20261111T100000',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART;TZID="Eastern Standard Time":20261112T090000',
            'DTEND;TZID="Eastern Standard Time":20261112T093000',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART;TZID=/Europe/Paris:20261113T090000',
            'DTEND;TZID=/Europe/Paris:20261113T100000',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART;TZID=Nowhere/Unknown:20261114T090000',
            'DTEND;TZID=Nowhere/Unknown:20261114T100000',
            'END:VEVENT',
        ));

        self::assertSame([
            ['2026-11-10T12:00:00Z', '2026-11-10T13:30:00Z', false],
            ['2026-11-11T14:00:00Z', '2026-11-11T15:00:00Z', false],
            ['2026-11-12T14:00:00Z', '2026-11-12T14:30:00Z', false],
            ['2026-11-13T08:00:00Z', '2026-11-13T09:00:00Z', false],
            ['2026-11-14T14:00:00Z', '2026-11-14T15:00:00Z', false],
        ], $this->times($periods));
    }

    public function testAZoneNamedWithControlCharactersIsTheCalendarZone(): void
    {
        $periods = self::read(self::calendar(
            'BEGIN:VEVENT',
            "DTSTART;TZID=Europe/\x00Paris:20261110T090000",
            'DTEND;TZID='.str_repeat('a', 65).':20261110T100000',
            'END:VEVENT',
        ));

        self::assertSame([['2026-11-10T14:00:00Z', '2026-11-10T15:00:00Z', false]], self::times($periods));
    }

    public function testWholeDaysLastTheirDaysInTheCalendarZone(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'DTSTART;VALUE=DATE:20261110',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART;VALUE=DATE:20261120',
            'DTEND;VALUE=DATE:20261122',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART;VALUE=DATE:20261125',
            'DURATION:P1W',
            'END:VEVENT',
        ));

        self::assertSame([
            ['2026-11-10T05:00:00Z', '2026-11-11T05:00:00Z', false],
            ['2026-11-20T05:00:00Z', '2026-11-22T05:00:00Z', false],
            ['2026-11-25T05:00:00Z', '2026-12-02T05:00:00Z', false],
        ], $this->times($periods));
    }

    public function testCancelledTransparentAndInstantEventsBlockNothingAndTentativeOnesAreSaidSo(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'DTSTART:20261110T120000Z',
            'DTEND:20261110T130000Z',
            'STATUS:CANCELLED',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART:20261110T140000Z',
            'DTEND:20261110T150000Z',
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART:20261110T160000Z',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART:20261110T170000Z',
            'DTEND:20261110T180000Z',
            'STATUS:tentative',
            'TRANSP:OPAQUE',
            'END:VEVENT',
        ));

        self::assertSame([['2026-11-10T17:00:00Z', '2026-11-10T18:00:00Z', true]], $this->times($periods));
    }

    public function testARuleIsExpandedInTheWindowWithItsAddedRemovedAndChangedOccurrences(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'UID:weekly',
            'DTSTART;TZID=America/Toronto:20261027T190000',
            'DTEND;TZID=America/Toronto:20261027T210000',
            'RRULE:FREQ=WEEKLY;COUNT=10',
            'EXDATE;TZID=America/Toronto:20261110T190000',
            'RDATE;TZID=America/Toronto:20261112T080000,20261113T080000/PT1H',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:weekly',
            'RECURRENCE-ID;TZID=America/Toronto:20261117T190000',
            'DTSTART;TZID=America/Toronto:20261118T190000',
            'DTEND;TZID=America/Toronto:20261118T200000',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:weekly',
            'RECURRENCE-ID;TZID=America/Toronto:20261124T190000',
            'DTSTART;TZID=America/Toronto:20261124T190000',
            'DTEND;TZID=America/Toronto:20261124T210000',
            'STATUS:CANCELLED',
            'END:VEVENT',
        ), new SyncWindow(new DateTimeImmutable('2026-11-01T00:00:00Z'), new DateTimeImmutable('2026-12-01T00:00:00Z')));

        // The occurrences keep 19:00 on the clock across the change of time; the changed one is read on its own, the
        // cancelled one gives nothing.
        self::assertSame([
            ['2026-11-04T00:00:00Z', '2026-11-04T02:00:00Z', false],
            ['2026-11-12T13:00:00Z', '2026-11-12T15:00:00Z', false],
            ['2026-11-13T13:00:00Z', '2026-11-13T15:00:00Z', false],
            ['2026-11-19T00:00:00Z', '2026-11-19T01:00:00Z', false],
        ], $this->times($periods));
    }

    public function testAnAddedDateWithoutARuleIsAnotherOccurrence(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'DTSTART:20261110T120000Z',
            'DTEND:20261110T130000Z',
            'RDATE:20261111T120000Z',
            'END:VEVENT',
        ));

        self::assertSame([
            ['2026-11-10T12:00:00Z', '2026-11-10T13:00:00Z', false],
            ['2026-11-11T12:00:00Z', '2026-11-11T13:00:00Z', false],
        ], $this->times($periods));
    }

    public function testAnOccurrenceThatStartsBeforeTheWindowAndEndsInItIsKept(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'DTSTART:20261031T220000Z',
            'DTEND:20261101T020000Z',
            'RRULE:FREQ=DAILY',
            'END:VEVENT',
        ), new SyncWindow(new DateTimeImmutable('2026-11-01T00:00:00Z'), new DateTimeImmutable('2026-11-02T00:00:00Z')));

        self::assertSame([
            ['2026-10-31T22:00:00Z', '2026-11-01T02:00:00Z', false],
            ['2026-11-01T22:00:00Z', '2026-11-02T02:00:00Z', false],
        ], $this->times($periods));
    }

    public function testFreeBusyTimesAreReadAndFreeOnesLeftOut(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VFREEBUSY',
            'DTSTART:20261101T000000Z',
            'FREEBUSY:20261110T120000Z/20261110T130000Z,20261110T140000Z/PT30M',
            'FREEBUSY;FBTYPE=BUSY-TENTATIVE:20261111T120000Z/+PT1H',
            'FREEBUSY;FBTYPE=FREE:20261112T120000Z/20261112T130000Z',
            'FREEBUSY;FBTYPE=BUSY-UNAVAILABLE:20261113T120000Z/20261113T130000Z',
            'END:VFREEBUSY',
        ));

        self::assertSame([
            ['2026-11-10T12:00:00Z', '2026-11-10T13:00:00Z', false],
            ['2026-11-10T14:00:00Z', '2026-11-10T14:30:00Z', false],
            ['2026-11-11T12:00:00Z', '2026-11-11T13:00:00Z', true],
            ['2026-11-13T12:00:00Z', '2026-11-13T13:00:00Z', false],
        ], $this->times($periods));
    }

    public function testFoldedLinesAlarmsBomAndLineEndsAreRead(): void
    {
        $text = "\u{FEFF}BEGIN:VCALENDAR\nVERSION:2.0\rBEGIN:VEVENT\r\nDTSTART:2026\r\n 1110T120000Z\r\nDTEND:20261110T1\n\t30000Z\r\n"
            ."BEGIN:VALARM\r\nTRIGGER:-PT15M\r\nDTSTART:20261201T000000Z\r\nEND:VALARM\r\n\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
            ."BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nDTSTART:20261111T120000Z\r\nDTEND:20261111T130000Z\r\nEND:VEVENT\r\nEND:VCALENDAR";

        self::assertSame([
            ['2026-11-10T12:00:00Z', '2026-11-10T13:00:00Z', false],
            ['2026-11-11T12:00:00Z', '2026-11-11T13:00:00Z', false],
        ], $this->times($this->read($text)));
    }

    public function testPeriodsOutsideTheWindowAreLeftOut(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'DTSTART:20261031T230000Z',
            'DTEND:20261101T000000Z',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART:20261201T000000Z',
            'DTEND:20261201T010000Z',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART:20261130T230000Z',
            'DTEND:20261201T010000Z',
            'END:VEVENT',
        ));

        self::assertSame([['2026-11-30T23:00:00Z', '2026-12-01T01:00:00Z', false]], $this->times($periods));
    }

    public function testParametersWithQuotedColonsAndSeveralValuesAreRead(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'ATTENDEE;CN="Doe: Jane";ROLE=REQ-PARTICIPANT:mailto:jane@example.com',
            'DTSTART;X-A="a:b",c;TZID=America/Toronto:20261110T190000',
            'DTEND;TZID=America/Toronto:20261110T200000',
            'END:VEVENT',
        ));

        self::assertSame([['2026-11-11T00:00:00Z', '2026-11-11T01:00:00Z', false]], $this->times($periods));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'not a calendar' => ["BEGIN:VCARD\r\nEND:VCARD", 'A calendar starts with BEGIN:VCALENDAR.'];
        yield 'empty' => ['', 'A calendar starts with BEGIN:VCALENDAR.'];
        yield 'something after' => ["BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\nX:1", 'A calendar holds nothing after END:VCALENDAR.'];
        yield 'unterminated' => ["BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nDTSTART:20261110T120000Z", 'A calendar ends with END:VCALENDAR.'];
        yield 'crossed' => ["BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nEND:VCALENDAR\r\nEND:VEVENT", 'The components of a calendar end in the order they begin.'];
        yield 'no colon' => ["BEGIN:VCALENDAR\r\nVERSION\r\nEND:VCALENDAR", 'A line of a calendar is a name, its parameters, a colon and a value.'];
        yield 'open quote' => ["BEGIN:VCALENDAR\r\nX;A=\"b:c\r\nEND:VCALENDAR", 'A line of a calendar is a name, its parameters, a colon and a value.'];
        yield 'bad parameter' => [self::calendar('BEGIN:VEVENT', 'DTSTART;TZID:20261110T120000Z', 'END:VEVENT'), 'A parameter of a calendar is a name, an equals sign and a value.'];
        yield 'no start' => [self::calendar('BEGIN:VEVENT', 'DTEND:20261110T120000Z', 'END:VEVENT'), 'An event of a calendar has a start.'];
        yield 'bad date' => [self::calendar('BEGIN:VEVENT', 'DTSTART:2026-11-10', 'END:VEVENT'), 'A date of a calendar is written 20261110 or 20261110T190000, with a Z or not.'];
        yield 'no such day' => [self::calendar('BEGIN:VEVENT', 'DTSTART:20260230T120000Z', 'END:VEVENT'), 'A date of a calendar is a day that exists.'];
        yield 'no such date' => [self::calendar('BEGIN:VEVENT', 'DTSTART;VALUE=DATE:20261341', 'END:VEVENT'), 'A date of a calendar is a day that exists.'];
        yield 'negative duration' => [self::calendar('BEGIN:VEVENT', 'DTSTART:20261110T120000Z', 'DURATION:-PT1H', 'END:VEVENT'), 'A duration of a calendar is written P1W, P1D, PT2H30M or P1DT2H, not negative.'];
        yield 'empty duration' => [self::calendar('BEGIN:VEVENT', 'DTSTART:20261110T120000Z', 'DURATION:PT', 'END:VEVENT'), 'A duration of a calendar is written P1W, P1D, PT2H30M or P1DT2H, not negative.'];
        yield 'unread rule' => [self::calendar('BEGIN:VEVENT', 'DTSTART:20261110T120000Z', 'DTEND:20261110T130000Z', 'RRULE:FREQ=HOURLY', 'END:VEVENT'), 'A recurrence rule of the calendar is not one phil/recurrence reads: "FREQ=HOURLY" is not a recurrence rule with a yearly, monthly, weekly or daily frequency.'];
        yield 'too deep' => ["BEGIN:VCALENDAR\r\n".str_repeat("BEGIN:X\r\n", 8), 'A calendar holds 20000 components at most, 8 deep.'];
        yield 'long property' => [self::calendar('BEGIN:VEVENT', 'DTSTART:20261110T120000Z', 'RDATE:'.str_repeat('20261110T120000Z,', 1200).'20261110T120000Z', 'END:VEVENT'), 'A property of a calendar read is 20000 bytes at most.'];
    }

    #[DataProvider('refusals')]
    public function testWhatIsNotACalendarIsRefused(string $text, string $message): void
    {
        $this->expectExceptionObject(InvalidCalendarData::because($message));

        $this->read($text);
    }

    public function testLongLinesThatAreNotReadAreSkipped(): void
    {
        $periods = $this->read(self::calendar('BEGIN:VEVENT', 'DTSTART:20261110T120000Z', 'DTEND:20261110T130000Z', 'DESCRIPTION:'.str_repeat('a', 30_000), 'END:VEVENT'));

        self::assertCount(1, $periods);
    }

    public function testATextTooLargeIsRefused(): void
    {
        $this->expectExceptionObject(InvalidCalendarData::because('A calendar is 10485760 bytes at most.'));

        $this->read(str_repeat('x', ICalendarReader::MAX_BYTES + 1));
    }

    public function testATextOfTheMostBytesIsRead(): void
    {
        $calendar = self::calendar('BEGIN:VEVENT', 'DTSTART:20261110T120000Z', 'DTEND:20261110T130000Z', 'END:VEVENT');
        $padding = 'X-PAD:'.str_repeat('a', ICalendarReader::MAX_BYTES - \strlen($calendar) - 8)."\r\n";

        self::assertCount(1, $this->read("BEGIN:VCALENDAR\r\n".$padding.substr($calendar, \strlen("BEGIN:VCALENDAR\r\n"))));
    }

    public function testTooManyComponentsAreRefused(): void
    {
        $this->expectExceptionObject(InvalidCalendarData::because('A calendar holds 20000 components at most, 8 deep.'));

        $this->read("BEGIN:VCALENDAR\r\n".str_repeat("BEGIN:VTODO\r\nEND:VTODO\r\n", ICalendarReader::MAX_COMPONENTS)."END:VCALENDAR\r\n");
    }

    public function testTheMostComponentsAreRead(): void
    {
        self::assertSame([], $this->read("BEGIN:VCALENDAR\r\n".str_repeat("BEGIN:VTODO\r\nEND:VTODO\r\n", ICalendarReader::MAX_COMPONENTS - 1)."END:VCALENDAR\r\n"));
    }

    public function testComponentsNestedToTheMostAreRead(): void
    {
        self::assertSame([], $this->read("BEGIN:VCALENDAR\r\n".str_repeat("BEGIN:X\r\n", 7).str_repeat("END:X\r\n", 7).'END:VCALENDAR'));
    }

    public function testTooManyPeriodsAreRefused(): void
    {
        $this->expectExceptionObject(InvalidCalendarData::because('A calendar gives 10000 busy periods in a window at most.'));

        $events = [];

        for ($i = 0; $i < 26; ++$i) {
            $events[] = 'BEGIN:VEVENT';
            $events[] = \sprintf('DTSTART:20261101T00%02d00Z', $i);
            $events[] = 'DURATION:PT1M';
            $events[] = 'RRULE:FREQ=DAILY';
            $events[] = 'END:VEVENT';
        }

        $this->read(self::calendar(...$events), SyncWindow::days(new DateTimeImmutable('2026-11-01T00:00:00Z'), 400));
    }

    public function testTooManyRepeatedEventsAreRefusedBeforeAnyIsExpanded(): void
    {
        $event = ['BEGIN:VEVENT', 'DTSTART:20261101T000000Z', 'DURATION:PT1M', 'RRULE:FREQ=DAILY;BYMONTH=2;BYMONTHDAY=30', 'END:VEVENT'];
        $started = hrtime(true);

        try {
            $this->read(self::calendar(...array_merge(...array_fill(0, ICalendarReader::MAX_RULES + 1, $event))));
            self::fail('Read.');
        } catch (InvalidCalendarData $invalidCalendarData) {
            self::assertSame('A calendar repeats 1000 events at most.', $invalidCalendarData->getMessage());
        }

        self::assertLessThan(1_000_000_000, hrtime(true) - $started);
    }

    public function testTheMostRepeatedEventsAreRead(): void
    {
        $repeated = ['BEGIN:VEVENT', 'DTSTART:20261101T000000Z', 'DURATION:PT1M', 'RRULE:FREQ=YEARLY;COUNT=1', 'END:VEVENT'];
        $single = ['BEGIN:VEVENT', 'DTSTART:20261102T000000Z', 'DURATION:PT1M', 'END:VEVENT'];
        $cancelled = ['BEGIN:VEVENT', 'DTSTART:20261103T000000Z', 'DURATION:PT1M', 'RRULE:FREQ=DAILY', 'STATUS:CANCELLED', 'END:VEVENT'];

        self::assertCount(
            ICalendarReader::MAX_RULES + 1,
            $this->read(self::calendar(...array_merge(...array_fill(0, ICalendarReader::MAX_RULES, $repeated), ...[$single, $cancelled]))),
        );
    }

    public function testTheRepeatedEventsOfSeveralTextsShareOneBudget(): void
    {
        $budget = new RuleBudget();
        $text = self::calendar(...array_merge(...array_fill(0, ICalendarReader::MAX_RULES / 2, ['BEGIN:VEVENT', 'DTSTART:20261101T000000Z', 'DURATION:PT1M', 'RRULE:FREQ=YEARLY;COUNT=1', 'END:VEVENT'])));
        $window = new SyncWindow(new DateTimeImmutable('2026-11-01T00:00:00Z'), new DateTimeImmutable('2026-12-01T00:00:00Z'));

        ICalendarReader::busy($text, $window, new DateTimeZone(self::TORONTO), $budget);
        ICalendarReader::busy($text, $window, new DateTimeZone(self::TORONTO), $budget);
        $this->expectExceptionObject(InvalidCalendarData::because('A calendar repeats 1000 events at most.'));

        ICalendarReader::busy(self::calendar('BEGIN:VEVENT', 'DTSTART:20261101T000000Z', 'RRULE:FREQ=DAILY', 'END:VEVENT'), $window, new DateTimeZone(self::TORONTO), $budget);
    }

    public function testTooManyDatesAreRefused(): void
    {
        $line = 'EXDATE:'.implode(',', array_fill(0, 1000, '20261110T120000Z'));
        $this->expectExceptionObject(InvalidCalendarData::because('An event of a calendar adds or removes 10000 dates at most.'));

        $this->read(self::calendar('BEGIN:VEVENT', 'DTSTART:20261110T120000Z', ...array_fill(0, 11, $line), ...['END:VEVENT']));
    }

    public function testTheMostDatesAreRead(): void
    {
        $line = 'EXDATE:'.implode(',', array_fill(0, 1000, '20261110T120000Z'));

        self::assertSame([], $this->read(self::calendar('BEGIN:VEVENT', 'DTSTART:20261110T120000Z', 'DTEND:20261110T130000Z', ...array_fill(0, 10, $line), ...['END:VEVENT'])));
    }

    public function testTheMostPeriodsAreRead(): void
    {
        $events = [];

        for ($i = 0; $i < BusyPeriods::MAX; ++$i) {
            $start = new DateTimeImmutable('2026-11-01T00:00:00Z')->modify(\sprintf('+%d minutes', 2 * $i));
            $events[] = 'BEGIN:VEVENT';
            $events[] = 'DTSTART:'.$start->format('Ymd\THis\Z');
            $events[] = 'DURATION:PT1M';
            $events[] = 'END:VEVENT';
        }

        self::assertCount(BusyPeriods::MAX, $this->read(self::calendar(...$events)));
    }

    public function testAnEventAfterAFreeBusyIsRead(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VFREEBUSY',
            'FREEBUSY:20261110T120000Z/20261110T130000Z',
            'END:VFREEBUSY',
            'BEGIN:VEVENT',
            'DTSTART:20261111T120000Z',
            'DURATION:PT1H',
            'END:VEVENT',
        ));

        self::assertSame([['2026-11-10T12:00:00Z', '2026-11-10T13:00:00Z', false], ['2026-11-11T12:00:00Z', '2026-11-11T13:00:00Z', false]], $this->times($periods));
    }

    public function testAnOverriddenOccurrenceIsReadOnItsOwnWhateverItHolds(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'UID:a',
            'DTSTART:20261110T120000Z',
            'RECURRENCE-ID:20261110T120000Z',
            'RRULE:FREQ=DAILY;COUNT=3',
            'DURATION:PT1H',
            'END:VEVENT',
        ));

        self::assertSame([['2026-11-10T12:00:00Z', '2026-11-10T13:00:00Z', false]], $this->times($periods));
    }

    public function testWhatIsWrittenAroundTheValuesIsLeftOut(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'UID:a ',
            'DTSTART:20261110T120000Z',
            'DURATION: PT1H ',
            'RRULE:FREQ=DAILY;COUNT=2 ',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:a',
            'RECURRENCE-ID:20261110T120000Z',
            'DTSTART:20261110T150000Z',
            'DURATION:PT1H',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART:20261120T120000Z',
            'DURATION:PT1H',
            'STATUS:CANCELLED ',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART:20261121T120000Z',
            'DURATION:PT1H',
            'TRANSP:TRANSPARENT ',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART:20261122T120000Z',
            'DURATION:PT1H',
            'TRANSP:transparent',
            'END:VEVENT',
        ));

        self::assertSame([['2026-11-11T12:00:00Z', '2026-11-11T13:00:00Z', false], ['2026-11-10T15:00:00Z', '2026-11-10T16:00:00Z', false]], $this->times($periods));
    }

    public function testAnEventFarBeforeTheWindowRepeatsIntoIt(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'DTSTART:19900101T120000Z',
            'DTEND:19900101T130000Z',
            'RRULE:FREQ=DAILY',
            'END:VEVENT',
        ), new SyncWindow(new DateTimeImmutable('2026-11-01T00:00:00Z'), new DateTimeImmutable('2026-11-03T00:00:00Z')));

        self::assertSame([['2026-11-01T12:00:00Z', '2026-11-01T13:00:00Z', false], ['2026-11-02T12:00:00Z', '2026-11-02T13:00:00Z', false]], $this->times($periods));
    }

    public function testAnOccurrenceLongerThanTheFirstOneByAChangeOfTimeIsRead(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'DTSTART;TZID=America/Toronto:20260901T100000',
            'DTEND;TZID=America/Toronto:20260908T100000',
            'RRULE:FREQ=WEEKLY;COUNT=20',
            'END:VEVENT',
        ), new SyncWindow(new DateTimeImmutable('2026-11-03T14:30:00Z'), new DateTimeImmutable('2026-11-04T00:00:00Z')));

        self::assertSame([['2026-10-27T14:00:00Z', '2026-11-03T15:00:00Z', false], ['2026-11-03T15:00:00Z', '2026-11-10T15:00:00Z', false]], $this->times($periods));
    }

    public function testThePeriodsOfAnEventAreBoundedByOneMore(): void
    {
        $start = new DateTimeImmutable('2026-11-01T00:00:00Z');
        $lines = [];

        for ($line = 0; $line < 10; ++$line) {
            $dates = [];

            for ($date = 1; $date <= 1000; ++$date) {
                $dates[] = $start->modify(\sprintf('+%d minutes', 1000 * $line + $date))->format('Ymd\THis\Z');
            }

            $lines[] = 'RDATE:'.implode(',', $dates);
        }

        $this->expectExceptionObject(InvalidCalendarData::because('A calendar gives 10000 busy periods in a window at most.'));

        $this->read(self::calendar('BEGIN:VEVENT', 'DTSTART:20261101T000000Z', 'DURATION:PT1S', ...$lines, ...['END:VEVENT']));
    }

    public function testTheTypeOfAFreeBusyPeriodIsReadInAnyCase(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VFREEBUSY',
            'FREEBUSY;FBTYPE=free:20261110T120000Z/20261110T130000Z',
            'FREEBUSY;FBTYPE=busy-tentative:20261111T120000Z/PT1H',
            'FREEBUSY;FBTYPE=Busy-Unavailable:20261112T120000Z/20261112T130000Z',
            'END:VFREEBUSY',
        ));

        self::assertSame([['2026-11-11T12:00:00Z', '2026-11-11T13:00:00Z', true], ['2026-11-12T12:00:00Z', '2026-11-12T13:00:00Z', false]], $this->times($periods));
    }

    public function testFreeBusyPeriodsAreReadWithTheirDurations(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VFREEBUSY',
            'FREEBUSY:20261110T120000Z/PT1H ,20261111T120000Z/P1DT2H3M4S,20261120T120000Z/+P1W',
            'END:VFREEBUSY',
        ));

        self::assertSame([
            ['2026-11-10T12:00:00Z', '2026-11-10T13:00:00Z', false],
            ['2026-11-11T12:00:00Z', '2026-11-12T14:03:04Z', false],
            ['2026-11-20T12:00:00Z', '2026-11-27T12:00:00Z', false],
        ], $this->times($periods));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badFreeBusy(): iterable
    {
        yield 'no end' => ['FREEBUSY:20261110T120000Z', 'A date of a calendar is written 20261110 or 20261110T190000, with a Z or not.'];
        yield 'three parts' => ['FREEBUSY:20261110T120000Z/20261110T130000Z/20261110T140000Z', 'A date of a calendar is written 20261110 or 20261110T190000, with a Z or not.'];
        yield 'a P inside' => ['FREEBUSY:20261110T120000Z/20261110P', 'A date of a calendar is written 20261110 or 20261110T190000, with a Z or not.'];
        yield 'junk before a day' => ['FREEBUSY:x20261110/20261111', 'A date of a calendar is written 20261110 or 20261110T190000, with a Z or not.'];
        yield 'junk before a time' => ['FREEBUSY:x20261110T120000Z/20261111T120000Z', 'A date of a calendar is written 20261110 or 20261110T190000, with a Z or not.'];
        yield 'junk after a time' => ['FREEBUSY:20261110T120000Zx/20261111T120000Z', 'A date of a calendar is written 20261110 or 20261110T190000, with a Z or not.'];
        yield 'a negative duration' => ['FREEBUSY:20261110T120000Z/-PT1H', 'A date of a calendar is written 20261110 or 20261110T190000, with a Z or not.'];
        yield 'an empty duration' => ['FREEBUSY:20261110T120000Z/P', 'A duration of a calendar is written P1W, P1D, PT2H30M or P1DT2H, not negative.'];
    }

    #[DataProvider('badFreeBusy')]
    public function testAFreeBusyThatCannotBeReadIsRefused(string $line, string $message): void
    {
        $this->expectExceptionObject(InvalidCalendarData::because($message));

        $this->read(self::calendar('BEGIN:VFREEBUSY', $line, 'END:VFREEBUSY'));
    }

    public function testDurationsAreReadInWeeksDaysHoursMinutesAndSeconds(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'DTSTART:20261110T120000Z',
            'DURATION:P1DT2H3M4S',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART:20261112T120000Z',
            'DURATION:P1W',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART:20261120T120000Z',
            'DURATION:P3D',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART:20261125T120000Z',
            'DURATION:PT5S',
            'END:VEVENT',
        ));

        self::assertSame([
            ['2026-11-10T12:00:00Z', '2026-11-11T14:03:04Z', false],
            ['2026-11-12T12:00:00Z', '2026-11-19T12:00:00Z', false],
            ['2026-11-20T12:00:00Z', '2026-11-23T12:00:00Z', false],
            ['2026-11-25T12:00:00Z', '2026-11-25T12:00:05Z', false],
        ], $this->times($periods));
    }

    public function testTooManyDatesAreRefusedFromTheFirstOneOver(): void
    {
        $line = 'EXDATE:'.implode(',', array_fill(0, 1000, '20261110T120000Z'));
        $this->expectExceptionObject(InvalidCalendarData::because('An event of a calendar adds or removes 10000 dates at most.'));

        $this->read(self::calendar('BEGIN:VEVENT', 'DTSTART:20261110T120000Z', ...array_fill(0, 10, $line), ...['EXDATE:20261110T120000Z', 'END:VEVENT']));
    }

    public function testADateIsTrimmedWherever(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'DTSTART: 20261110T120000Z ',
            'DURATION:PT1H',
            'END:VEVENT',
        ));

        self::assertSame([['2026-11-10T12:00:00Z', '2026-11-10T13:00:00Z', false]], $this->times($periods));
    }

    public function testALocalTimeWithoutAnEndIsNoWholeDay(): void
    {
        self::assertSame([], $this->read(self::calendar('BEGIN:VEVENT', 'DTSTART:20261110T120000', 'END:VEVENT')));
        self::assertCount(1, $this->read(self::calendar('BEGIN:VEVENT', 'DTSTART:20261110', 'END:VEVENT')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badMoments(): iterable
    {
        yield 'junk before a day' => ['x20261110', 'A date of a calendar is written 20261110 or 20261110T190000, with a Z or not.'];
        yield 'junk before a time' => ['x20261110T120000', 'A date of a calendar is written 20261110 or 20261110T190000, with a Z or not.'];
        yield 'junk after a time' => ['20261110T120000Zx', 'A date of a calendar is written 20261110 or 20261110T190000, with a Z or not.'];
        yield 'a day that does not exist' => ['20261131', 'A date of a calendar is a day that exists.'];
    }

    #[DataProvider('badMoments')]
    public function testAMomentThatCannotBeReadIsRefused(string $value, string $message): void
    {
        $this->expectExceptionObject(InvalidCalendarData::because($message));

        $this->read(self::calendar('BEGIN:VEVENT', 'DTSTART:'.$value, 'END:VEVENT'));
    }

    public function testAZoneIsTrimmedAndItsParametersAreReadInAnyCase(): void
    {
        $periods = $this->read(self::calendar(
            'BEGIN:VEVENT',
            'DTSTART;TZID=" Europe/Paris":20261110T090000',
            'DURATION:PT1H',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART;tzid=Europe/Paris:20261111T090000',
            'DURATION:PT1H',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART;VALUE=DATE-TIME;TZID=Europe/Paris:20261112T090000',
            'DURATION:PT1H',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART;TZID="Europe/Paris":20261113T090000',
            'DURATION:PT1H',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'DTSTART;TZID="":20261114T090000',
            'DURATION:PT1H',
            'END:VEVENT',
        ));

        self::assertSame([
            ['2026-11-10T08:00:00Z', '2026-11-10T09:00:00Z', false],
            ['2026-11-11T08:00:00Z', '2026-11-11T09:00:00Z', false],
            ['2026-11-12T08:00:00Z', '2026-11-12T09:00:00Z', false],
            ['2026-11-13T08:00:00Z', '2026-11-13T09:00:00Z', false],
            ['2026-11-14T14:00:00Z', '2026-11-14T15:00:00Z', false],
        ], $this->times($periods));
    }

    public function testNamesAndValuesAreReadInAnyCaseAndTrimmed(): void
    {
        $periods = $this->read(implode("\r\n", ['begin:vcalendar', 'begin:vevent ', 'dtstart:20261110T120000Z', 'duration:PT1H', 'end:vevent', 'end:vcalendar ', '']));

        self::assertSame([['2026-11-10T12:00:00Z', '2026-11-10T13:00:00Z', false]], $this->times($periods));
    }

    public function testAPropertyOfTheMostBytesIsRead(): void
    {
        $line = 'UID:'.str_repeat('a', ICalendarReader::MAX_LINE - 4);
        $periods = $this->read(self::calendar('BEGIN:VEVENT', 'DTSTART:20261110T120000Z', 'DURATION:PT1H', $line, 'END:VEVENT'));

        self::assertCount(1, $periods);

        $this->expectExceptionObject(InvalidCalendarData::because('A property of a calendar read is 20000 bytes at most.'));
        $this->read(self::calendar('BEGIN:VEVENT', 'DTSTART:20261110T120000Z', $line.'a', 'END:VEVENT'));
    }

    private static function calendar(string ...$lines): string
    {
        return implode("\r\n", ['BEGIN:VCALENDAR', 'VERSION:2.0', ...$lines, 'END:VCALENDAR'])."\r\n";
    }

    /**
     * @return list<BusyPeriod>
     */
    private function read(string $text, ?SyncWindow $window = null): array
    {
        return ICalendarReader::busy($text, $window ?? new SyncWindow(new DateTimeImmutable('2026-11-01T00:00:00Z'), new DateTimeImmutable('2026-12-01T00:00:00Z')), new DateTimeZone(self::TORONTO));
    }

    /**
     * @param list<BusyPeriod> $periods
     *
     * @return list<array{string, string, bool}>
     */
    private function times(array $periods): array
    {
        return array_map(static fn (BusyPeriod $period): array => [$period->start->format('Y-m-d\TH:i:s\Z'), $period->end->format('Y-m-d\TH:i:s\Z'), $period->tentative], $periods);
    }
}
