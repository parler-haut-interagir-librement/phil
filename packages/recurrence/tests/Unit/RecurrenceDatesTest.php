<?php

declare(strict_types=1);

namespace Phil\Recurrence\Tests\Unit;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Phil\Recurrence\Exception\InvalidRule;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceDates;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecurrenceDates::class)]
final class RecurrenceDatesTest extends TestCase
{
    /**
     * @param list<string> $dates
     */
    private function assertDates(array $dates, RecurrenceDates $line): void
    {
        self::assertSame($dates, array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d H:i:s e'), $line->dates));
    }

    public function testTimesInANamedZoneAreReadAndWrittenBack(): void
    {
        $line = RecurrenceDates::parse('rdate;tzid=America/Toronto:20261224T190000,20261231T190000');

        self::assertSame('RDATE', $line->property);
        self::assertFalse($line->isDate);
        self::assertSame('America/Toronto', $line->timezone?->getName());
        $this->assertDates(['2026-12-24 19:00:00 America/Toronto', '2026-12-31 19:00:00 America/Toronto'], $line);
        self::assertSame('RDATE;TZID=America/Toronto:20261224T190000,20261231T190000', (string) $line);
    }

    public function testUtcTimesAreReadAndWrittenInUtc(): void
    {
        $line = RecurrenceDates::parse("EXDATE:20261224T000000Z,\r\n 20261231T000000Z", new DateTimeZone('Europe/Paris'));

        self::assertSame('EXDATE', $line->property);
        self::assertNull($line->timezone);
        $this->assertDates(['2026-12-24 00:00:00 UTC', '2026-12-31 00:00:00 UTC'], $line);
        self::assertSame('EXDATE:20261224T000000Z,20261231T000000Z', (string) $line);
    }

    public function testDaysAreReadAndWrittenAsDays(): void
    {
        $line = RecurrenceDates::parse('RDATE;VALUE=DATE:20261224,20261226', new DateTimeZone('America/Toronto'));

        self::assertTrue($line->isDate);
        self::assertSame('America/Toronto', $line->timezone?->getName());
        $this->assertDates(['2026-12-24 00:00:00 America/Toronto', '2026-12-26 00:00:00 America/Toronto'], $line);
        self::assertSame('RDATE;VALUE=DATE:20261224,20261226', (string) $line);
        self::assertSame('RDATE;VALUE=DATE:20261224', (string) RecurrenceDates::parse('RDATE;VALUE=DATE;TZID=Asia/Tokyo:20261224'));
        self::assertSame('Asia/Tokyo', RecurrenceDates::parse('RDATE;VALUE=DATE;TZID=Asia/Tokyo:20261224')->timezone?->getName());
        self::assertNull(RecurrenceDates::parse('RDATE;VALUE=DATE:20261224')->timezone);
    }

    public function testFloatingTimesAreReadInTheZoneGivenAndKeepIt(): void
    {
        $line = RecurrenceDates::parse('RDATE:20261224T190000', new DateTimeZone('America/Vancouver'));

        $this->assertDates(['2026-12-24 19:00:00 America/Vancouver'], $line);
        self::assertSame('RDATE;TZID=America/Vancouver:20261224T190000', (string) $line);

        $default = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');

        try {
            self::assertSame('RDATE;TZID=Europe/Paris:20261224T190000', (string) RecurrenceDates::parse('RDATE;VALUE=DATE-TIME:20261224T190000'));
        } finally {
            date_default_timezone_set($default);
        }
    }

    public function testSpacesAroundTheLineAndItsDatesAreIgnored(): void
    {
        self::assertSame('RDATE:20261224T190000Z,20261231T190000Z', (string) RecurrenceDates::parse(" \tRDATE:20261224T190000Z, 20261231T190000Z \r\n"));
    }

    public function testTheKindOfDatesIsReadInAnyCase(): void
    {
        self::assertTrue(RecurrenceDates::parse('RDATE;VALUE=date:20261224')->isDate);
        self::assertFalse(RecurrenceDates::parse('RDATE;VALUE=Date-Time:20261224T190000Z')->isDate);
    }

    public function testANamedZoneWinsOverTheZoneGiven(): void
    {
        $line = RecurrenceDates::parse('RDATE;TZID=America/Toronto:20261224T190000', new DateTimeZone('Europe/Paris'));

        $this->assertDates(['2026-12-24 19:00:00 America/Toronto'], $line);
        self::assertSame('RDATE;TZID=America/Toronto:20261224T190000', (string) $line);
    }

    public function testOneUtcTimeIsWrittenInUtcWhateverTheZoneGiven(): void
    {
        $line = RecurrenceDates::parse('EXDATE:20261224T000000Z', new DateTimeZone('Europe/Paris'));

        self::assertNull($line->timezone);
        self::assertSame('EXDATE:20261224T000000Z', (string) $line);
    }

    public function testAQuotedZoneIsRead(): void
    {
        self::assertSame('Europe/Paris', RecurrenceDates::parse('RDATE;TZID="Europe/Paris":20261224T190000')->timezone?->getName());
    }

    public function testDatesAreBuiltAndWrittenInTheirZone(): void
    {
        $dates = [new DateTime('2026-12-24 19:00', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-12-25 00:00 UTC')];

        self::assertSame('RDATE:20261225T000000Z,20261225T000000Z', (string) RecurrenceDates::of('rdate', $dates));
        self::assertSame('EXDATE;TZID=Europe/Paris:20261225T010000,20261225T010000', (string) RecurrenceDates::of('EXDATE', $dates, timezone: new DateTimeZone('Europe/Paris')));
        self::assertSame('EXDATE:20261225T000000Z', (string) RecurrenceDates::of('EXDATE', [$dates[1]], timezone: new DateTimeZone('UTC')));
        self::assertSame('RDATE;VALUE=DATE:20261224', (string) RecurrenceDates::of('RDATE', [$dates[0]], true, new DateTimeZone('Asia/Tokyo')));
        self::assertSame('RDATE;TZID="+05:30":20261225T053000', (string) RecurrenceDates::of('RDATE', [$dates[1]], timezone: new DateTimeZone('+05:30')));
        self::assertSame('RDATE;TZID=+05:30:20261225T053000', str_replace('"', '', (string) RecurrenceDates::parse('RDATE;TZID="+05:30":20261225T053000')));
    }

    public function testALineReadIsTheLineWritten(): void
    {
        self::assertSame('RDATE;TZID=America/Toronto:20260308T033000', (string) RecurrenceDates::parse('RDATE;TZID=America/Toronto:20260308T023000'), 'a time a change of time skips is the time after it');

        foreach (['RDATE;TZID=America/Toronto:20260308T033000', 'EXDATE:00011231T235959Z', 'RDATE;VALUE=DATE:99991231', 'RDATE;TZID="+05:30":20261225T053000'] as $text) {
            self::assertSame($text, (string) RecurrenceDates::parse((string) RecurrenceDates::parse($text)));
        }
    }

    public function testParsedDatesAreAddedToARecurrence(): void
    {
        $start = new DateTimeImmutable('2026-11-02 09:00', new DateTimeZone('America/Toronto'));
        $recurrence = Recurrence::of($start, RecurrenceRule::parse('FREQ=WEEKLY;COUNT=2'), RecurrenceDates::parse('EXDATE;TZID=America/Toronto:20261109T090000')->dates, RecurrenceDates::parse('RDATE;TZID=America/Toronto:20261105T180000')->dates);

        self::assertSame(['11-02 09:00', '11-05 18:00'], array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('m-d H:i'), $recurrence->between()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'no colon' => ['RDATE 20261224T190000Z', 'is not a line of dates'];
        yield 'no name' => [':20261224T190000Z', 'is not a line of dates'];
        yield 'another property' => ['DTSTART:20261224T190000Z', 'added with RDATE or taken away with EXDATE'];
        yield 'no date' => ['RDATE:', 'is not a time'];
        yield 'an empty date' => ['RDATE:20261224T190000Z,', 'is not a time'];
        yield 'a period' => ['RDATE;VALUE=PERIOD:19960403T020000Z/19960403T040000Z', 'Periods of a line of dates are not read.'];
        yield 'an unknown kind' => ['RDATE;VALUE=TEXT:x', '"TEXT" is not a kind of dates'];
        yield 'an unknown parameter' => ['RDATE;X-FOO=1:20261224T190000Z', 'The parameter X-FOO of a line of dates is not read.'];
        yield 'a parameter twice' => ['RDATE;TZID=UTC;tzid=UTC:20261224T190000', 'The parameter TZID of a line of dates is given twice.'];
        yield 'an unknown zone' => ['RDATE;TZID=Eastern Standard Time:20261224T190000', '"Eastern Standard Time" is not a time zone PHP knows.'];
        yield 'a zone with a null byte' => ["RDATE;TZID=Europe/Paris\0:20261224T190000", 'is not a time zone PHP knows.'];
        yield 'an empty zone' => ['RDATE;TZID=:20261224T190000', '"" is not a time zone PHP knows.'];
        yield 'a zone too long' => ['RDATE;TZID='.str_repeat('A', 65).':20261224T190000', 'is not a time zone PHP knows.'];
        yield 'a time among days' => ['RDATE;VALUE=DATE:20261224T190000', 'is not a day'];
        yield 'a day among times' => ['RDATE:20261224', '"20261224" is not a time'];
        yield 'a month 13' => ['RDATE:20261324T190000Z', 'is not a date of the years 1 to 9999'];
        yield 'a 25th hour' => ['RDATE:20261224T250000Z', 'is not a date of the years 1 to 9999'];
        yield 'a 61st minute' => ['RDATE:20261224T196000Z', 'is not a date of the years 1 to 9999'];
        yield 'a 61st second' => ['RDATE:20261224T190060Z', 'is not a date of the years 1 to 9999'];
        yield 'a time after other characters' => ['RDATE:X20261224T190000Z', '"X20261224T190000Z" is not a time'];
        yield 'a zone after a null byte' => ["RDATE;TZID=\0Europe/Paris:20261224T190000", 'is not a time zone PHP knows.'];
        yield 'the year 0' => ['RDATE;VALUE=DATE:00001224', 'is not a date of the years 1 to 9999'];
        yield 'a short time' => ['RDATE:20261224T1900Z', 'is not a time'];
        yield 'a time with an offset' => ['RDATE:20261224T190000+0100', 'is not a time'];
        yield 'a trailing line' => ["RDATE:20261224T190000Z\nRRULE:FREQ=DAILY", 'is not a time'];
    }

    #[DataProvider('unreadable')]
    public function testAnUnreadableLineIsRefused(string $line, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        RecurrenceDates::parse($line);
    }

    public function testALineHoldsAtMostSoManyDates(): void
    {
        $dates = implode(',', array_fill(0, RecurrenceDates::MAX_DATES, '20261224T190000Z'));

        self::assertCount(RecurrenceDates::MAX_DATES, RecurrenceDates::parse('RDATE:'.$dates)->dates);

        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('A list of dates holds at most 1000.');

        RecurrenceDates::parse('RDATE;VALUE=DATE:'.implode(',', array_fill(0, RecurrenceDates::MAX_DATES + 1, '20261224')));
    }

    public function testTooManyDatesAreRefusedBeforeAnyIsRead(): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('A list of dates holds at most 1000.');

        RecurrenceDates::parse('RDATE;VALUE=DATE:'.implode(',', array_fill(0, RecurrenceDates::MAX_DATES, '20261224')).',x');
    }

    public function testALineLongerThanTheLimitIsRefusedUnread(): void
    {
        $line = 'RDATE:'.str_repeat('20261224T190000Z,', 1029).'20261224T190000Z';

        self::assertGreaterThan(RecurrenceDates::MAX_LENGTH, \strlen($line));
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('is not a line of dates');

        RecurrenceDates::parse($line);
    }

    public function testALineOfTheLimitIsRead(): void
    {
        $line = 'RDATE:'.str_repeat('X', RecurrenceDates::MAX_LENGTH - 6);

        try {
            RecurrenceDates::parse($line);
            self::fail('A line of letters was read.');
        } catch (InvalidRule $invalidRule) {
            self::assertStringContainsString('is not a time', $invalidRule->getMessage());
        }
    }

    public function testAListIsBuiltWithAtLeastOneDateAndAtMostTheLimit(): void
    {
        try {
            RecurrenceDates::of('RDATE', []);
            self::fail('An empty list was built.');
        } catch (InvalidRule $invalidRule) {
            self::assertSame('A list of dates holds at least one.', $invalidRule->getMessage());
        }

        $date = new DateTimeImmutable('2026-12-24 19:00 UTC');
        self::assertCount(RecurrenceDates::MAX_DATES, RecurrenceDates::of('RDATE', array_fill(0, RecurrenceDates::MAX_DATES, $date))->dates);

        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('A list of dates holds at most 1000.');
        RecurrenceDates::of('RDATE', array_fill(0, RecurrenceDates::MAX_DATES + 1, $date));
    }

    public function testADateOutsideTheYears1To9999IsRefused(): void
    {
        $past = new DateTimeImmutable('9999-12-31 23:00 UTC')->modify('+1 day');

        foreach ([[$past, false, null], [new DateTimeImmutable('9999-12-31 23:00 UTC'), false, new DateTimeZone('Asia/Tokyo')], [new DateTimeImmutable('0001-01-01 00:00 UTC'), false, new DateTimeZone('America/Toronto')], [$past, true, null]] as [$moment, $isDate, $zone]) {
            try {
                RecurrenceDates::of('RDATE', [$moment], $isDate, $zone);
                self::fail($moment->format('c').' was kept.');
            } catch (InvalidRule $invalidRule) {
                self::assertSame('A date of a recurrence is in the years 1 to 9999.', $invalidRule->getMessage());
            }
        }

        self::assertSame('RDATE;VALUE=DATE:99991231', (string) RecurrenceDates::of('RDATE', [new DateTimeImmutable('9999-12-31 23:00 UTC')], true, new DateTimeZone('Asia/Tokyo')));
        self::assertSame('RDATE:99991231T230000Z', (string) RecurrenceDates::of('RDATE', [new DateTimeImmutable('9999-12-31 23:00', new DateTimeZone('UTC'))]));
    }
}
