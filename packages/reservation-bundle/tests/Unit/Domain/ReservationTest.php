<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\Exception\InvalidDefinition;
use Phil\Reservation\Interval;
use Phil\ReservationBundle\Domain\Exception\InvalidReservation;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Model\Reservation;
use Phil\ReservationBundle\Domain\Status\ReservationStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Reservation::class)]
#[CoversClass(InvalidReservation::class)]
final class ReservationTest extends TestCase
{
    private function room(): BookableResource
    {
        $room = BookableResource::create('Salle', 4, 'America/Toronto');
        $room->holdFor(20);

        return $room;
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }

    private function span(string $start = '2026-11-10 09:00', string $end = '2026-11-10 10:00'): Interval
    {
        return new Interval($this->at($start), $this->at($end));
    }

    public function testAHoldKeepsItsUnitsUntilItExpires(): void
    {
        $now = new DateTimeImmutable('2026-11-01 17:00', new DateTimeZone('UTC'));
        $hold = Reservation::hold($this->room(), $this->span(), 2, 'account-7', $now);

        self::assertTrue(Uuid::isValid($hold->id));
        self::assertSame(ReservationStatus::Held, $hold->status());
        self::assertSame(2, $hold->quantity());
        self::assertSame('account-7', $hold->holder());
        self::assertSame('2026-11-01T12:20:00-05:00', $hold->holdExpiresAt()?->format(\DATE_ATOM));
        self::assertSame('2026-11-01T12:00:00-05:00', $hold->createdAt()->format(\DATE_ATOM));
        self::assertSame('2026-11-10T09:00:00-05:00', $hold->interval()->start->format(\DATE_ATOM));
        self::assertSame('2026-11-10T10:00:00-05:00', $hold->interval()->end->format(\DATE_ATOM));
        self::assertTrue($hold->holdsUnits($now->modify('+19 minutes +59 seconds')));
        self::assertFalse($hold->holdsUnits($now->modify('+20 minutes')));
        self::assertNull($hold->lastReminder());

        $booking = $hold->booking();
        self::assertSame([$hold->id, 2], [$booking->id, $booking->quantity]);
        self::assertSame($hold->interval()->from, $booking->interval->from);
    }

    public function testMomentsAreKeptInUtcAndGivenBackOnTheResourcesClock(): void
    {
        $paris = new DateTimeZone('Europe/Paris');
        $reservation = Reservation::confirmed($this->room(), new Interval(new DateTimeImmutable('2026-11-10 15:00', $paris), new DateTimeImmutable('2026-11-10 16:00', $paris)), 1, null, new DateTimeImmutable('2026-11-01 12:00', $paris));

        self::assertSame('2026-11-10T09:00:00-05:00', $reservation->interval()->start->format(\DATE_ATOM));
        self::assertSame('America/Toronto', $reservation->interval()->end->getTimezone()->getName());
        self::assertSame('2026-11-01T06:00:00-05:00', $reservation->createdAt()->format(\DATE_ATOM));
        self::assertNull($reservation->holdExpiresAt());
        self::assertNull($reservation->holder());
        self::assertSame(ReservationStatus::Confirmed, $reservation->status());
        self::assertTrue($reservation->holdsUnits(new DateTimeImmutable('2030-01-01')));
    }

    public function testTheHolderIsAnIdentifier(): void
    {
        self::assertSame(str_repeat('a', 255), Reservation::confirmed($this->room(), $this->span(), 1, str_repeat('a', 255), $this->at('2026-11-01'))->holder());

        foreach (['', str_repeat('é', 128)] as $holder) {
            try {
                Reservation::hold($this->room(), $this->span(), 1, $holder, $this->at('2026-11-01'));
                self::fail('The holder is refused.');
            } catch (InvalidReservation $invalidReservation) {
                self::assertSame('The holder of a reservation is an identifier of 1 to 255 bytes.', $invalidReservation->getMessage());
            }
        }

        foreach (['ada@example.com', 'a.b+c@mail.example.org', 'é@exemple.québec'] as $holder) {
            try {
                Reservation::confirmed($this->room(), $this->span(), 1, $holder, $this->at('2026-11-01'));
                self::fail('An e-mail address is refused: '.$holder);
            } catch (InvalidReservation $invalidReservation) {
                self::assertSame('The holder of a reservation is a stable identifier, never an e-mail address.', $invalidReservation->getMessage());
            }
        }

        foreach (['account@tenant', 'order@7.', '@example.com', 'a@b@example.com', 'ada@example.com x', 'urn:account:7'] as $holder) {
            self::assertSame($holder, Reservation::hold($this->room(), $this->span(), 1, $holder, $this->at('2026-11-01'))->holder());
        }
    }

    public function testTheQuantityIsAtLeastOne(): void
    {
        $this->expectException(InvalidDefinition::class);

        Reservation::confirmed($this->room(), $this->span(), 0, null, $this->at('2026-11-01'));
    }

    public function testAHoldIsConfirmedExpiredOrNot(): void
    {
        $now = $this->at('2026-11-01 12:00');
        $hold = Reservation::hold($this->room(), $this->span(), 1, null, $now);
        $hold->confirm();
        self::assertSame(ReservationStatus::Confirmed, $hold->status());
        self::assertNull($hold->holdExpiresAt());

        $expired = Reservation::hold($this->room(), $this->span(), 1, null, $now);
        self::assertFalse($expired->expire($now->modify('+19 minutes')));
        self::assertSame(ReservationStatus::Held, $expired->status());
        self::assertTrue($expired->expire($now->modify('+20 minutes')));
        self::assertSame(ReservationStatus::Expired, $expired->status());
        self::assertFalse($expired->expire($now->modify('+1 day')));
        self::assertFalse($expired->holdsUnits($now));
        $expired->confirm();
        self::assertSame(ReservationStatus::Confirmed, $expired->status());
        self::assertFalse($expired->expire($now->modify('+1 day')));

        $this->expectExceptionObject(InvalidReservation::because('Only a hold is confirmed, not a reservation confirmed.'));

        $expired->confirm();
    }

    public function testACancelledReservationIsNotConfirmed(): void
    {
        $reservation = Reservation::hold($this->room(), $this->span(), 1, null, $this->at('2026-11-01 12:00'));
        $reservation->cancel();
        self::assertSame(ReservationStatus::Cancelled, $reservation->status());
        self::assertNull($reservation->holdExpiresAt());
        self::assertFalse($reservation->holdsUnits($this->at('2026-11-01 12:00')));

        $this->expectExceptionObject(InvalidReservation::because('Only a hold is confirmed, not a reservation cancelled.'));

        $reservation->confirm();
    }

    public function testOnlyALiveReservationIsCancelledOrMoved(): void
    {
        $confirmed = Reservation::confirmed($this->room(), $this->span(), 1, null, $this->at('2026-11-01 12:00'));
        $confirmed->cancel();

        try {
            $confirmed->cancel();
            self::fail('A cancelled reservation is not cancelled again.');
        } catch (InvalidReservation $invalidReservation) {
            self::assertSame('A reservation cancelled is not cancelled.', $invalidReservation->getMessage());
        }

        $expired = Reservation::hold($this->room(), $this->span(), 1, null, $this->at('2026-11-01 12:00'));
        $expired->expire($this->at('2026-11-02'));
        $this->expectExceptionObject(InvalidReservation::because('A reservation expired is not moved.'));

        $expired->reschedule($this->span('2026-11-11 09:00', '2026-11-11 10:00'), 1);
    }

    public function testItIsMovedAndItsRemindersStartOver(): void
    {
        $reservation = Reservation::confirmed($this->room(), $this->span(), 1, null, $this->at('2026-11-01 12:00'));
        $reservation->remind(60);
        self::assertSame(60, $reservation->lastReminder());

        $reservation->reschedule($this->span('2026-11-11 14:00', '2026-11-11 16:00'), 3);
        self::assertSame('2026-11-11T14:00:00-05:00', $reservation->interval()->start->format(\DATE_ATOM));
        self::assertSame('2026-11-11T16:00:00-05:00', $reservation->interval()->end->format(\DATE_ATOM));
        self::assertSame(3, $reservation->quantity());
        self::assertNull($reservation->lastReminder());

        $held = Reservation::hold($this->room(), $this->span(), 1, null, $this->at('2026-11-01 12:00'));
        $held->reschedule($this->span('2026-11-12 09:00', '2026-11-12 10:00'), 1);
        self::assertSame('2026-11-12', $held->interval()->start->format('Y-m-d'));

        try {
            $reservation->reschedule($this->span(), 0);
            self::fail('A quantity of zero is refused.');
        } catch (InvalidDefinition) {
            self::assertSame(3, $reservation->quantity());
            self::assertSame('2026-11-11', $reservation->interval()->start->format('Y-m-d'));
        }
    }
}
