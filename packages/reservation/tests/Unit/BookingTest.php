<?php

declare(strict_types=1);

namespace Phil\Reservation\Tests\Unit;

use DateTimeImmutable;
use Phil\Reservation\Booking;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Decision;
use Phil\Reservation\Exception\InvalidDefinition;
use Phil\Reservation\Interval;
use Phil\Reservation\Violation;
use Phil\Reservation\ViolationCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Booking::class)]
#[CoversClass(BookingRequest::class)]
#[CoversClass(Decision::class)]
#[CoversClass(Violation::class)]
#[CoversClass(InvalidDefinition::class)]
final class BookingTest extends TestCase
{
    private function span(): Interval
    {
        return new Interval(new DateTimeImmutable('2026-11-10 09:00'), new DateTimeImmutable('2026-11-10 10:00'));
    }

    public function testABookingKeepsWhatItHolds(): void
    {
        $booking = new Booking('b-1', $this->span(), 3);

        self::assertSame('b-1', $booking->id);
        self::assertSame(3, $booking->quantity);
        self::assertSame(1, new Booking('b-2', $this->span())->quantity);
    }

    public function testABookingHasAnIdentifier(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('A booking has an identifier.');

        new Booking('', $this->span());
    }

    public function testABookingHoldsAtLeastOneUnit(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('A booking holds at least one unit, not 0.');

        new Booking('b-1', $this->span(), 0);
    }

    public function testARequestKeepsItsParts(): void
    {
        $now = new DateTimeImmutable('2026-11-01 12:00');
        $request = new BookingRequest($this->span(), $now, 2, 'b-1');

        self::assertSame($now, $request->requestedAt);
        self::assertSame(2, $request->quantity);
        self::assertSame('b-1', $request->replacing);
        self::assertNull(new BookingRequest($this->span(), $now)->replacing);
        self::assertSame(1, new BookingRequest($this->span(), $now)->quantity);
    }

    public function testADecisionWithoutViolationAllowsTheRequest(): void
    {
        $allowed = new Decision([], ['b-1']);
        $refused = new Decision([new Violation(ViolationCode::TooSoon, ['minutes' => 60]), new Violation(ViolationCode::Full, ['free' => 0])]);

        self::assertTrue($allowed->allowed());
        self::assertSame(['b-1'], $allowed->conflicts);
        self::assertFalse($allowed->has(ViolationCode::Full));
        self::assertFalse($refused->allowed());
        self::assertTrue($refused->has(ViolationCode::TooSoon));
        self::assertTrue($refused->has(ViolationCode::Full));
        self::assertFalse($refused->has(ViolationCode::OffGrid));
        self::assertSame(['minutes' => 60], $refused->violations[0]->parameters);
        self::assertSame([], new Violation(ViolationCode::Unavailable)->parameters);
        self::assertSame([], new Decision()->conflicts);
        self::assertTrue(new Decision()->allowed());
    }
}
