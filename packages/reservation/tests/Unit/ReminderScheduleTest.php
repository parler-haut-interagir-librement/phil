<?php

declare(strict_types=1);

namespace Phil\Reservation\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\Exception\InvalidDefinition;
use Phil\Reservation\Interval;
use Phil\Reservation\ReminderSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReminderSchedule::class)]
final class ReminderScheduleTest extends TestCase
{
    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }

    private function booking(): Interval
    {
        return new Interval($this->at('2026-11-10 19:00'), $this->at('2026-11-10 22:00'));
    }

    public function testOffsetsAreKeptOnceTheFurthestFirst(): void
    {
        self::assertSame([1440, 60, 15], new ReminderSchedule(60, 1440, 15, 60)->offsets);
        self::assertSame([], new ReminderSchedule()->offsets);
    }

    public function testItGivesTheMomentsOfTheReminders(): void
    {
        $moments = new ReminderSchedule(60, 1440)->moments($this->booking());

        self::assertSame(['2026-11-09T19:00:00-05:00', '2026-11-10T18:00:00-05:00'], array_map(static fn (DateTimeImmutable $moment): string => $moment->format(\DATE_ATOM), $moments));
    }

    public function testItGivesTheReminderDueNow(): void
    {
        $schedule = new ReminderSchedule(1440, 60, 15);
        $booking = $this->booking();

        self::assertNull($schedule->due($booking, $this->at('2026-11-09 18:59:59')));
        self::assertSame(1440, $schedule->due($booking, $this->at('2026-11-09 19:00')));
        self::assertNull($schedule->due($booking, $this->at('2026-11-10 17:59'), 1440));
        self::assertSame(60, $schedule->due($booking, $this->at('2026-11-10 18:00'), 1440));
        self::assertNull($schedule->due($booking, $this->at('2026-11-10 18:10'), 60));
        self::assertSame(15, $schedule->due($booking, $this->at('2026-11-10 18:45'), 60));
        self::assertNull($schedule->due($booking, $this->at('2026-11-10 18:50'), 15));
    }

    public function testAReminderWhoseMomentPassedIsOvertaken(): void
    {
        $schedule = new ReminderSchedule(1440, 60, 15);
        $booking = $this->booking();

        self::assertSame(60, $schedule->due($booking, $this->at('2026-11-10 18:30')));
        self::assertSame(15, $schedule->due($booking, $this->at('2026-11-10 18:50'), 1440));
    }

    public function testNoReminderOnceTheBookingStarted(): void
    {
        $schedule = new ReminderSchedule(15);

        self::assertSame(15, $schedule->due($this->booking(), $this->at('2026-11-10 18:59:59')));
        self::assertNull($schedule->due($this->booking(), $this->at('2026-11-10 19:00')));
        self::assertNull(new ReminderSchedule()->due($this->booking(), $this->at('2026-11-10 18:00')));
    }

    public function testAnOffsetIsWithinAYear(): void
    {
        self::assertSame([525_600, 1], new ReminderSchedule(1, 525_600)->offsets);

        foreach ([0, 525_601] as $offset) {
            try {
                new ReminderSchedule(60, $offset);
                self::fail('The offset is refused.');
            } catch (InvalidDefinition $exception) {
                self::assertSame(\sprintf('A reminder comes from 1 to 525600 minutes before the start, not %d.', $offset), $exception->getMessage());
            }
        }
    }

    public function testABookingGetsTwentyRemindersAtMost(): void
    {
        self::assertCount(20, new ReminderSchedule(...range(1, 20))->offsets);
        self::assertCount(20, new ReminderSchedule(...[...range(1, 20), 20])->offsets);
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('A booking gets at most 20 reminders.');

        new ReminderSchedule(...range(1, 21));
    }
}
