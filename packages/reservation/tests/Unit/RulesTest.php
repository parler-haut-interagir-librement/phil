<?php

declare(strict_types=1);

namespace Phil\Reservation\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Exception\InvalidDefinition;
use Phil\Reservation\Interval;
use Phil\Reservation\Rule\DurationLimits;
use Phil\Reservation\Rule\MaximumAdvance;
use Phil\Reservation\Rule\MinimumNotice;
use Phil\Reservation\Rule\SlotGrid;
use Phil\Reservation\ViolationCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MinimumNotice::class)]
#[CoversClass(MaximumAdvance::class)]
#[CoversClass(DurationLimits::class)]
#[CoversClass(SlotGrid::class)]
final class RulesTest extends TestCase
{
    private function request(string $start, string $end, string $now = '2026-11-10 08:00:00'): BookingRequest
    {
        $zone = new DateTimeZone('America/Toronto');

        return new BookingRequest(new Interval(new DateTimeImmutable($start, $zone), new DateTimeImmutable($end, $zone)), new DateTimeImmutable($now, $zone));
    }

    private function zone(): DateTimeZone
    {
        return new DateTimeZone('America/Toronto');
    }

    public function testANoticeKeepsBookingsFarEnoughAhead(): void
    {
        $rule = new MinimumNotice(60);

        self::assertNull($rule->check($this->request('2026-11-10 09:00', '2026-11-10 10:00'), $this->zone()));
        $violation = $rule->check($this->request('2026-11-10 08:59:59', '2026-11-10 10:00'), $this->zone());
        self::assertSame(ViolationCode::TooSoon, $violation?->code);
        self::assertSame(['minutes' => 60], $violation->parameters);
        self::assertNull(new MinimumNotice(0)->check($this->request('2026-11-10 08:00', '2026-11-10 10:00'), $this->zone()));
        self::assertSame(ViolationCode::TooSoon, new MinimumNotice(0)->check($this->request('2026-11-10 07:59:59', '2026-11-10 10:00'), $this->zone())?->code);
        self::assertSame(60, $rule->minutes);
    }

    public function testANoticeIsNotNegative(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('A notice is not negative: -1 minutes.');

        new MinimumNotice(-1);
    }

    public function testAnAdvanceKeepsBookingsCloseEnough(): void
    {
        $rule = new MaximumAdvance(120);

        self::assertNull($rule->check($this->request('2026-11-10 10:00', '2026-11-10 11:00'), $this->zone()));
        $violation = $rule->check($this->request('2026-11-10 10:00:01', '2026-11-10 11:00'), $this->zone());
        self::assertSame(ViolationCode::TooFarAhead, $violation?->code);
        self::assertSame(['minutes' => 120], $violation->parameters);
        self::assertNull(new MaximumAdvance(1)->check($this->request('2026-11-10 08:01', '2026-11-10 11:00'), $this->zone()));
        self::assertSame(120, $rule->minutes);
    }

    public function testAnAdvanceIsAtLeastAMinute(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('A booking window is at least one minute, not 0.');

        new MaximumAdvance(0);
    }

    public function testLimitsKeepTheLengthBetweenTheShortestAndTheLongest(): void
    {
        $rule = new DurationLimits(30, 120);

        self::assertNull($rule->check($this->request('2026-11-10 09:00', '2026-11-10 09:30'), $this->zone()));
        self::assertNull($rule->check($this->request('2026-11-10 09:00', '2026-11-10 11:00'), $this->zone()));
        $short = $rule->check($this->request('2026-11-10 09:00', '2026-11-10 09:29:59'), $this->zone());
        self::assertSame(ViolationCode::TooShort, $short?->code);
        self::assertSame(['minutes' => 30], $short->parameters);
        $long = $rule->check($this->request('2026-11-10 09:00', '2026-11-10 11:00:01'), $this->zone());
        self::assertSame(ViolationCode::TooLong, $long?->code);
        self::assertSame(['minutes' => 120], $long->parameters);
        self::assertNull(new DurationLimits()->check($this->request('2026-11-10 09:00', '2026-11-12 09:00'), $this->zone()));
        self::assertSame(ViolationCode::TooShort, new DurationLimits()->check($this->request('2026-11-10 09:00', '2026-11-10 09:00:59'), $this->zone())?->code);
        self::assertNull(new DurationLimits(1, 1)->check($this->request('2026-11-10 09:00', '2026-11-10 09:01'), $this->zone()));
        self::assertSame(1, new DurationLimits()->minimum);
        self::assertNull(new DurationLimits()->maximum);
    }

    /**
     * @return iterable<string, array{int, ?int, string}>
     */
    public static function badLimits(): iterable
    {
        yield 'no shortest' => [0, null, 'The shortest booking lasts at least one minute, not 0.'];
        yield 'longest below shortest' => [30, 29, 'The longest booking, 29 minutes, is not shorter than the shortest, 30.'];
    }

    #[DataProvider('badLimits')]
    public function testLimitsAreConsistent(int $minimum, ?int $maximum, string $message): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains($message);

        new DurationLimits($minimum, $maximum);
    }

    public function testAGridAlignsStartsAndLengthsOnTheResourcesClock(): void
    {
        $grid = new SlotGrid(30);

        self::assertNull($grid->check($this->request('2026-11-10 09:30', '2026-11-10 10:30'), $this->zone()));
        self::assertNull($grid->check($this->request('2026-11-10 00:00', '2026-11-10 00:30'), $this->zone()));
        $off = $grid->check($this->request('2026-11-10 09:10', '2026-11-10 10:10'), $this->zone());
        self::assertSame(ViolationCode::OffGrid, $off?->code);
        self::assertSame(['minutes' => 30], $off->parameters);
        self::assertSame(ViolationCode::OffGrid, $grid->check($this->request('2026-11-10 09:00', '2026-11-10 09:45'), $this->zone())?->code);
        self::assertSame(ViolationCode::OffGrid, $grid->check($this->request('2026-11-10 09:00:30', '2026-11-10 09:30:30'), $this->zone())?->code);
        // 14:30 UTC is 9:30 in Toronto and 20:00 in Kolkata: on the grid of an hour in Kolkata only.
        $utc = new BookingRequest(new Interval(new DateTimeImmutable('2026-11-10 14:30', new DateTimeZone('UTC')), new DateTimeImmutable('2026-11-10 15:30', new DateTimeZone('UTC'))), new DateTimeImmutable());
        self::assertNull($grid->check($utc, $this->zone()));
        self::assertNull(new SlotGrid(60)->check($utc, new DateTimeZone('Asia/Kolkata')));
        self::assertSame(ViolationCode::OffGrid, new SlotGrid(60)->check($utc, $this->zone())?->code);
        self::assertSame(ViolationCode::OffGrid, new SlotGrid(60)->check($this->request('2026-11-10 13:00', '2026-11-10 13:30'), $this->zone())?->code);
        self::assertNull(new SlotGrid(60)->check($this->request('2026-11-10 13:00', '2026-11-10 15:00'), $this->zone()));
        self::assertNull(new SlotGrid(1440)->check($this->request('2026-11-10 00:00', '2026-11-12 00:00'), $this->zone()));
        self::assertNull(new SlotGrid(1)->check($this->request('2026-11-10 09:01', '2026-11-10 09:02'), $this->zone()));
        self::assertSame(ViolationCode::OffGrid, new SlotGrid(1)->check($this->request('2026-11-10 09:01:30', '2026-11-10 09:02:30'), $this->zone())?->code);
        // Kathmandu is 5:45 ahead of UTC: 14:15 UTC is 20:00 there.
        $nepal = new BookingRequest(new Interval(new DateTimeImmutable('2026-11-10 14:15', new DateTimeZone('UTC')), new DateTimeImmutable('2026-11-10 15:15', new DateTimeZone('UTC'))), new DateTimeImmutable());
        self::assertNull(new SlotGrid(60)->check($nepal, new DateTimeZone('Asia/Kathmandu')));
        self::assertSame(ViolationCode::OffGrid, new SlotGrid(60)->check($nepal, new DateTimeZone('UTC'))?->code);
        self::assertSame(30, $grid->minutes);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badSteps(): iterable
    {
        yield 'zero' => [0];
        yield 'not a divisor' => [7];
        yield 'longer than a day' => [2880];
    }

    #[DataProvider('badSteps')]
    public function testAGridStepDividesADay(int $minutes): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A grid step divides a day in minutes, from 1 to 1440: not %d.', $minutes));

        new SlotGrid($minutes);
    }
}
