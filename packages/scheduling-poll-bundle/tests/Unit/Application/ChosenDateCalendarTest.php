<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Application\ChosenDateCalendar;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The file is checked as RFC 5545 asks: CRLF line ends, lines of 75 octets at most, the components opened and closed
 * in order, the required properties of a calendar and of an event, and the times in UTC.
 */
#[CoversClass(ChosenDateCalendar::class)]
final class ChosenDateCalendarTest extends TestCase
{
    private const string POLL = '0195a6c4-0000-7000-8000-000000000001';

    public function testTheDateChosenIsAValidCalendarOfOneEventInUtc(): void
    {
        $poll = new SchedulingPoll(self::POLL, 'Game night; bring dice, snacks', ResourceReference::of('user', 'ada'), 'America/Toronto', new DateTimeImmutable('2026-10-30 UTC'), new DateTimeImmutable('2026-10-20 UTC'), "Second floor\nRing twice. ".str_repeat('é', 60), 'The back room');
        // The night Toronto leaves summer time: 2 hours on the wall, 3 hours in fact.
        $toronto = new DateTimeZone('America/Toronto');
        $slot = new Slot('0195a6c4-0000-7000-8000-000000000002', self::POLL, 0, new DateTimeImmutable('2026-10-31 23:30', $toronto), new DateTimeImmutable('2026-11-01 01:30', $toronto)->modify('+1 hour'));
        $file = ChosenDateCalendar::file($poll, $slot, new DateTimeImmutable('2026-10-30 12:00', $toronto));

        self::assertStringEndsWith("\r\n", $file);
        self::assertStringNotContainsString("\n", str_replace("\r\n", '', $file));

        $folded = explode("\r\n", rtrim($file, "\r\n"));

        foreach ($folded as $line) {
            self::assertLessThanOrEqual(75, \strlen($line), $line);
            self::assertTrue(mb_check_encoding($line, 'UTF-8'), 'A line is folded inside a character.');
        }

        $lines = preg_split('/\r\n/', rtrim(preg_replace('/\r\n[ \t]/', '', $file) ?? '', "\r\n"));
        self::assertIsArray($lines);
        $properties = [];

        foreach ($lines as $line) {
            [$name, $value] = explode(':', $line, 2);
            $properties[$name][] = $value;
        }

        self::assertSame(['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:'.ChosenDateCalendar::PRODUCT], \array_slice($lines, 0, 3));
        self::assertSame(['END:VEVENT', 'END:VCALENDAR'], \array_slice($lines, -2));
        self::assertSame(['VCALENDAR', 'VEVENT'], $properties['BEGIN']);
        self::assertSame(['VEVENT', 'VCALENDAR'], $properties['END']);
        self::assertSame([ChosenDateCalendar::UID_PREFIX.self::POLL], $properties['UID']);
        self::assertSame(['20261030T160000Z'], $properties['DTSTAMP']);
        self::assertSame(['20261101T033000Z'], $properties['DTSTART']);
        self::assertSame(['20261101T073000Z'], $properties['DTEND']);
        self::assertSame(['Game night\; bring dice\, snacks'], $properties['SUMMARY']);
        self::assertSame(['Second floor\nRing twice. '.str_repeat('é', 60)], $properties['DESCRIPTION']);
        self::assertSame(['The back room'], $properties['LOCATION']);
    }

    public function testAPollWithoutDescriptionOrPlaceWritesNeither(): void
    {
        $poll = new SchedulingPoll(self::POLL, 'Game night', ResourceReference::of('user', 'ada'), 'UTC', new DateTimeImmutable('2026-10-30 UTC'), new DateTimeImmutable('2026-10-20 UTC'));
        $slot = new Slot('0195a6c4-0000-7000-8000-000000000002', self::POLL, 0, new DateTimeImmutable('2026-11-01 19:00 UTC'), new DateTimeImmutable('2026-11-01 21:00 UTC'));
        $file = ChosenDateCalendar::file($poll, $slot, new DateTimeImmutable('2026-10-30 UTC'));

        self::assertStringNotContainsString('DESCRIPTION', $file);
        self::assertStringNotContainsString('LOCATION', $file);
        self::assertStringContainsString("DTSTART:20261101T190000Z\r\n", $file);
    }
}
