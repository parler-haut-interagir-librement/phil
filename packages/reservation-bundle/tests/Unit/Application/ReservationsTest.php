<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Application;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\Interval;
use Phil\Reservation\Violation;
use Phil\Reservation\ViolationCode;
use Phil\ReservationBundle\Application\Exception\NotFound;
use Phil\ReservationBundle\Application\Exception\ReservationRefused;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Domain\Event\ReservationCancelled;
use Phil\ReservationBundle\Domain\Event\ReservationConfirmed;
use Phil\ReservationBundle\Domain\Event\ReservationExpired;
use Phil\ReservationBundle\Domain\Event\ReservationHeld;
use Phil\ReservationBundle\Domain\Event\ReservationRescheduled;
use Phil\ReservationBundle\Domain\Exception\InvalidReservation;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Status\ReservationStatus;
use Phil\ReservationBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\ReservationBundle\Tests\Unit\Fixture\InMemoryReservations;
use Phil\ReservationBundle\Tests\Unit\Fixture\InMemoryResources;
use Phil\ReservationBundle\Tests\Unit\Fixture\RecordingEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Reservations::class)]
#[CoversClass(ReservationRefused::class)]
#[CoversClass(NotFound::class)]
#[CoversClass(ReservationHeld::class)]
#[CoversClass(ReservationConfirmed::class)]
#[CoversClass(ReservationCancelled::class)]
#[CoversClass(ReservationExpired::class)]
#[CoversClass(ReservationRescheduled::class)]
final class ReservationsTest extends TestCase
{
    private InMemoryResources $resources;

    private InMemoryReservations $reservations;

    private FrozenClock $clock;

    private RecordingEvents $events;

    private BookableResource $table;

    protected function setUp(): void
    {
        $this->resources = new InMemoryResources();
        $this->reservations = new InMemoryReservations();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-11-01 12:00', new DateTimeZone('America/Toronto')));
        $this->events = new RecordingEvents();
        $this->table = BookableResource::create('Table 4', 8, 'America/Toronto');
        $this->table->holdFor(10);

        $this->resources->save($this->table);
    }

    private function service(bool $events = true): Reservations
    {
        return new Reservations($this->resources, $this->reservations, $this->clock, $events ? $this->events : null);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }

    /**
     * @return list<string>
     */
    private function events(): array
    {
        return array_map(static fn (object $event): string => new ReflectionClass($event)->getShortName(), $this->events->events);
    }

    /**
     * @return array{string, string} the reservation and resource of the first event
     */
    private function firstEventIds(): array
    {
        $event = $this->events->events[0] ?? null;
        self::assertTrue($event instanceof ReservationHeld || $event instanceof ReservationConfirmed || $event instanceof ReservationCancelled || $event instanceof ReservationExpired || $event instanceof ReservationRescheduled);

        return [$event->reservationId, $event->resourceId];
    }

    private function firstAsked(): Interval
    {
        $asked = $this->reservations->asked[0] ?? null;
        self::assertNotNull($asked);

        return $asked;
    }

    public function testABookingIsCheckedUnderTheResourcesLockAndSaved(): void
    {
        $reservation = $this->service()->book($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'), 3, 'account-1');

        self::assertSame(ReservationStatus::Confirmed, $reservation->status());
        self::assertSame(3, $reservation->quantity());
        self::assertSame('account-1', $reservation->holder());
        self::assertSame($reservation, $this->reservations->get($reservation->id));
        self::assertSame([$this->table->id], $this->reservations->locked);
        self::assertSame(['ReservationConfirmed'], $this->events());
        self::assertSame([$reservation->id, $this->table->id], $this->firstEventIds());
    }

    public function testAHoldKeepsItsUnitsForTheResourcesHoldTime(): void
    {
        $hold = $this->service()->hold($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'), 8);

        self::assertSame(ReservationStatus::Held, $hold->status());
        self::assertSame('2026-11-01 12:10', $hold->holdExpiresAt()?->format('Y-m-d H:i'));
        self::assertSame(['ReservationHeld'], $this->events());
        self::assertSame([$hold->id, $this->table->id], $this->firstEventIds());
        self::assertNull($hold->holder());
        self::assertSame(1, $this->service()->hold($this->table->id, $this->at('2026-11-10 21:00'), $this->at('2026-11-10 22:00'))->quantity());

        try {
            $this->service()->hold($this->table->id, $this->at('2026-11-10 20:00'), $this->at('2026-11-10 22:00'));
            self::fail('The table is full.');
        } catch (ReservationRefused $reservationRefused) {
            self::assertTrue($reservationRefused->decision->has(ViolationCode::Full));
            self::assertSame([$hold->id, array_key_last($this->reservations->reservations)], $reservationRefused->decision->conflicts);
            self::assertSame('The reservation is refused: full.', $reservationRefused->getMessage());
        }

        // Once the hold expired, its seats are free.
        $this->clock->now = $this->at('2026-11-01 12:10');
        self::assertSame(8, $this->service()->book($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'), 8)->quantity());
    }

    public function testTheTimesAreReadOnTheResourcesClock(): void
    {
        $paris = new DateTimeZone('Europe/Paris');
        $reservation = $this->service()->book($this->table->id, new DateTimeImmutable('2026-11-10 15:00', $paris), new DateTime('2026-11-10 16:00', $paris));

        self::assertSame('2026-11-10T09:00:00-05:00', $reservation->interval()->start->format(\DATE_ATOM));
        self::assertSame('2026-11-10T10:00:00-05:00', $reservation->interval()->end->format(\DATE_ATOM));
    }

    public function testTheBookingsReadSpanTheBuffersOfBothSides(): void
    {
        $this->table->buffer(10, 20);
        $this->service()->check($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'));

        self::assertSame('2026-11-10 18:30', $this->firstAsked()->start->format('Y-m-d H:i'));
        self::assertSame('2026-11-10 21:30', $this->firstAsked()->end->format('Y-m-d H:i'));
    }

    public function testTheBuffersOfTwoNeighboursAddUp(): void
    {
        $room = BookableResource::create('Salle Verne', 1, 'America/Toronto');
        $room->buffer(15, 15);

        $this->resources->save($room);
        $this->service()->book($room->id, $this->at('2026-11-10 10:00'), $this->at('2026-11-10 11:00'));

        // Fifteen minutes after the first booking and fifteen before the second: thirty minutes between them.
        foreach (['2026-11-10 11:29', '2026-11-10 11:15', '2026-11-10 11:00'] as $start) {
            self::assertFalse($this->service()->check($room->id, $this->at($start), $this->at('2026-11-10 12:00'))->allowed(), $start);
        }

        self::assertTrue($this->service()->check($room->id, $this->at('2026-11-10 11:30'), $this->at('2026-11-10 12:00'))->allowed());
        self::assertFalse($this->service()->check($room->id, $this->at('2026-11-10 09:00'), $this->at('2026-11-10 09:31'))->allowed());
        self::assertTrue($this->service()->check($room->id, $this->at('2026-11-10 09:00'), $this->at('2026-11-10 09:30'))->allowed());
    }

    public function testAPersonIsAResourceLikeARoom(): void
    {
        // A game master, booked at one table of a convention: the same rules keep them from a second table at once.
        $gameMaster = BookableResource::create('Game master 12', 1, 'America/Toronto');
        $gameMaster->buffer(0, 15);

        $this->resources->save($gameMaster);
        $this->service()->book($gameMaster->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 22:00'), holder: 'table-4');

        try {
            $this->service()->book($gameMaster->id, $this->at('2026-11-10 21:00'), $this->at('2026-11-10 23:00'), holder: 'table-7');
            self::fail('A person is not at two tables at once.');
        } catch (ReservationRefused $reservationRefused) {
            self::assertSame([ViolationCode::Full], array_map(static fn (Violation $violation): ViolationCode => $violation->code, $reservationRefused->decision->violations));
        }

        self::assertSame('table-7', $this->service()->book($gameMaster->id, $this->at('2026-11-10 22:15'), $this->at('2026-11-10 23:00'), holder: 'table-7')->holder());
    }

    public function testACheckBooksNothing(): void
    {
        $this->table->restrict(minimumNotice: 60 * 24 * 30);
        $decision = $this->service()->check($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'), 9);

        self::assertSame([ViolationCode::Quantity, ViolationCode::TooSoon], array_map(static fn (Violation $violation): ViolationCode => $violation->code, $decision->violations));
        self::assertSame([], $this->reservations->reservations);
        self::assertSame([], $this->events->events);
        self::assertTrue($this->service()->check($this->table->id, $this->at('2026-12-10 19:00'), $this->at('2026-12-10 21:00'))->allowed());

        // One unit is checked when no quantity is given.
        $this->service()->book($this->table->id, $this->at('2026-12-10 19:00'), $this->at('2026-12-10 21:00'), 7);
        self::assertTrue($this->service()->check($this->table->id, $this->at('2026-12-10 19:00'), $this->at('2026-12-10 21:00'))->allowed());
    }

    public function testAMoveCountsTheNoticeFromNow(): void
    {
        $this->table->restrict(minimumNotice: 60);
        $reservation = $this->service()->book($this->table->id, $this->at('2026-11-01 13:30'), $this->at('2026-11-01 15:00'));
        $this->clock->now = $this->at('2026-11-01 13:00');

        // From when it was booked the notice would be met; a move is a new request, made now.
        try {
            $this->service()->reschedule($reservation->id, $this->at('2026-11-01 13:30'), $this->at('2026-11-01 14:30'));
            self::fail('Half an hour is less than the notice.');
        } catch (ReservationRefused $reservationRefused) {
            self::assertTrue($reservationRefused->decision->has(ViolationCode::TooSoon));
        }

        self::assertFalse($this->service()->check($this->table->id, $this->at('2026-11-01 13:30'), $this->at('2026-11-01 14:30'), 1, $reservation->id)->allowed());
    }

    public function testACheckLeavesOutTheReservationReplaced(): void
    {
        $first = $this->service()->book($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'), 8);

        self::assertFalse($this->service()->check($this->table->id, $this->at('2026-11-10 20:00'), $this->at('2026-11-10 22:00'), 8)->allowed());
        self::assertTrue($this->service()->check($this->table->id, $this->at('2026-11-10 20:00'), $this->at('2026-11-10 22:00'), 8, $first->id)->allowed());
    }

    public function testAnUnknownOrInactiveResourceTakesNoReservation(): void
    {
        try {
            $this->service()->book('nope', $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'));
            self::fail('The resource does not exist.');
        } catch (NotFound $notFound) {
            self::assertSame('No active resource "nope".', $notFound->getMessage());
        }

        $this->table->activate(false);
        $this->expectExceptionObject(NotFound::resource($this->table->id));

        $this->service()->slots($this->table->id, $this->at('2026-11-10 00:00'), $this->at('2026-11-11 00:00'), 60);
    }

    public function testAnUnknownReservationIsNotFound(): void
    {
        try {
            $this->service()->cancel('a'.str_repeat('é', 69));
            self::fail('The reservation does not exist.');
        } catch (NotFound $notFound) {
            // The identifier quoted is cut at its first 64 characters, not bytes.
            self::assertSame('No reservation "a'.str_repeat('é', 63).'".', $notFound->getMessage());
        }
    }

    public function testTheResourceNotFoundIsQuotedShort(): void
    {
        self::assertSame('No active resource "a'.str_repeat('é', 63).'".', NotFound::resource('a'.str_repeat('é', 69))->getMessage());
        self::assertSame('No reservation "'.str_repeat('é', 64).'".', NotFound::reservation(str_repeat('é', 64))->getMessage());
    }

    public function testAHoldIsConfirmed(): void
    {
        $hold = $this->service()->hold($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'), 2);
        $this->reservations->locked = [];
        $this->events->events = [];
        $saves = $this->reservations->saves;

        $confirmed = $this->service()->confirm($hold->id);
        self::assertSame($saves + 1, $this->reservations->saves);

        self::assertSame($hold, $confirmed);
        self::assertSame(ReservationStatus::Confirmed, $confirmed->status());
        self::assertSame([$this->table->id], $this->reservations->locked);
        self::assertSame(['ReservationConfirmed'], $this->events());
        self::assertSame([$hold->id, $this->table->id], $this->firstEventIds());
    }

    public function testAnExpiredHoldIsCheckedAgainWhenConfirmed(): void
    {
        $this->table->restrict(minimumNotice: 60);
        $hold = $this->service()->hold($this->table->id, $this->at('2026-11-01 13:30'), $this->at('2026-11-01 15:00'), 5);

        // An hour later the notice is not met any more, but it counts from when the hold was made.
        $this->clock->now = $this->at('2026-11-01 13:00');
        self::assertSame(ReservationStatus::Confirmed, $this->service()->confirm($hold->id)->status());

        $late = $this->service()->hold($this->table->id, $this->at('2026-11-02 19:00'), $this->at('2026-11-02 21:00'), 5);
        $this->clock->now = $this->at('2026-11-01 13:30');
        $this->service()->expireHolds();
        $this->service()->book($this->table->id, $this->at('2026-11-02 19:00'), $this->at('2026-11-02 21:00'), 4);

        try {
            $this->service()->confirm($late->id);
            self::fail('The seats were taken meanwhile.');
        } catch (ReservationRefused $reservationRefused) {
            self::assertSame(['free' => 4], $reservationRefused->decision->violations[0]->parameters);
            self::assertSame(ReservationStatus::Expired, $late->status());
        }
    }

    public function testAnExpiredHoldNotYetMarkedIsCheckedAgain(): void
    {
        $hold = $this->service()->hold($this->table->id, $this->at('2026-11-02 19:00'), $this->at('2026-11-02 21:00'), 5);
        $this->clock->now = $this->at('2026-11-01 12:30');
        $this->service()->book($this->table->id, $this->at('2026-11-02 19:00'), $this->at('2026-11-02 21:00'), 4);

        $this->expectException(ReservationRefused::class);

        $this->service()->confirm($hold->id);
    }

    public function testACancelledReservationIsNotConfirmedNorCheckedAgain(): void
    {
        $reservation = $this->service()->book($this->table->id, $this->at('2026-11-02 19:00'), $this->at('2026-11-02 21:00'), 5);
        $this->service()->cancel($reservation->id);
        $this->reservations->asked = [];

        try {
            $this->service()->confirm($reservation->id);
            self::fail('A cancelled reservation is not confirmed.');
        } catch (InvalidReservation) {
            self::assertSame([], $this->reservations->asked);
        }
    }

    public function testAReservationIsMovedAndResized(): void
    {
        $reservation = $this->service()->book($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'), 6);
        $this->service()->book($this->table->id, $this->at('2026-11-10 21:00'), $this->at('2026-11-10 23:00'), 2);
        $this->events->events = [];
        $this->reservations->locked = [];
        $saves = $this->reservations->saves;

        $moved = $this->service()->reschedule($reservation->id, $this->at('2026-11-10 20:00'), $this->at('2026-11-10 22:00'));
        self::assertSame($saves + 1, $this->reservations->saves);
        self::assertSame('20:00', $moved->interval()->start->format('H:i'));
        self::assertSame(6, $moved->quantity());
        self::assertSame(['ReservationRescheduled'], $this->events());
        self::assertSame([$reservation->id, $this->table->id], $this->firstEventIds());
        self::assertSame([$this->table->id], $this->reservations->locked);

        try {
            $this->service()->reschedule($reservation->id, $this->at('2026-11-10 20:00'), $this->at('2026-11-10 22:00'), 7);
            self::fail('Seven and two make more than eight.');
        } catch (ReservationRefused) {
            self::assertSame(6, $moved->quantity());
        }

        self::assertSame(2, $this->service()->reschedule($reservation->id, $this->at('2026-11-10 20:00'), $this->at('2026-11-10 22:00'), 2)->quantity());
    }

    public function testAReservationIsCancelled(): void
    {
        $reservation = $this->service()->book($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'), 8);
        $this->events->events = [];
        $saves = $this->reservations->saves;

        self::assertSame(ReservationStatus::Cancelled, $this->service()->cancel($reservation->id)->status());
        self::assertSame($saves + 1, $this->reservations->saves);
        self::assertSame(['ReservationCancelled'], $this->events());
        self::assertSame([$reservation->id, $this->table->id], $this->firstEventIds());
        self::assertTrue($this->service()->check($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'), 8)->allowed());
    }

    public function testTheFreeSlotsLeaveOutTheBookedOnes(): void
    {
        $this->table->resize(1);
        $this->table->buffer(0, 30);
        $this->service()->book($this->table->id, $this->at('2026-11-10 10:00'), $this->at('2026-11-10 11:00'));
        $this->reservations->asked = [];

        $slots = $this->service()->slots($this->table->id, $this->at('2026-11-10 09:00'), $this->at('2026-11-10 13:00'), 60, step: 30);
        self::assertSame(['11:30', '12:00'], array_map(static fn (Interval $slot): string => $slot->start->format('H:i'), $slots));
        self::assertSame('2026-11-10 08:30', $this->firstAsked()->start->format('Y-m-d H:i'));
        self::assertSame('2026-11-10 13:30', $this->firstAsked()->end->format('Y-m-d H:i'));
        self::assertCount(1, $this->service()->slots($this->table->id, $this->at('2026-11-10 09:00'), $this->at('2026-11-10 13:00'), 60, 1, 30, 1));
        self::assertSame([], $this->service()->slots($this->table->id, $this->at('2026-11-10 09:00'), $this->at('2026-11-10 13:00'), 60, 2));
        self::assertSame(['12:00'], array_map(static fn (Interval $slot): string => $slot->start->format('H:i'), $this->service()->slots($this->table->id, $this->at('2026-11-10 11:30'), $this->at('2026-11-10 13:30'), 60)));
        self::assertCount(100, $this->service()->slots($this->table->id, $this->at('2026-11-11 00:00'), $this->at('2026-11-12 00:00'), 1));
        self::assertSame('2026-11-01 12:00', $this->clock->now()->format('Y-m-d H:i'));
    }

    public function testTheFreeSlotsFollowTheRulesFromNow(): void
    {
        $this->table->restrict(minimumNotice: 120);
        $slots = $this->service()->slots($this->table->id, $this->at('2026-11-01 12:00'), $this->at('2026-11-01 16:00'), 60);

        self::assertSame(['14:00', '15:00'], array_map(static fn (Interval $slot): string => $slot->start->format('H:i'), $slots));
    }

    public function testHoldsWhoseMomentCameAreMarkedExpired(): void
    {
        $first = $this->service()->hold($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'));
        $this->clock->now = $this->at('2026-11-01 12:05');
        $second = $this->service()->hold($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'));
        $this->events->events = [];
        $this->clock->now = $this->at('2026-11-01 12:10');
        $saves = $this->reservations->saves;

        self::assertSame(1, $this->service()->expireHolds(20));
        self::assertSame($saves + 1, $this->reservations->saves);
        self::assertSame([20], $this->reservations->limits);
        self::assertSame(ReservationStatus::Expired, $first->status());
        self::assertSame(ReservationStatus::Held, $second->status());
        self::assertSame(['ReservationExpired'], $this->events());
        self::assertSame([$first->id, $this->table->id], $this->firstEventIds());
        self::assertSame(0, $this->service()->expireHolds());
        self::assertSame([20, 500], $this->reservations->limits);
    }

    public function testAMarkedHoldIsNotExpiredTwice(): void
    {
        $hold = $this->service()->hold($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'));
        $this->clock->now = $this->at('2026-11-02 00:00');
        $hold->expire($this->clock->now);
        $hold->confirm();

        $saves = $this->reservations->saves;

        // A repository that still lists it: the reservation itself says it is not a hold any more.
        $this->reservations->listedExpired = [$hold];
        $this->events->events = [];

        self::assertSame(0, $this->service()->expireHolds());
        self::assertSame($saves, $this->reservations->saves);
        self::assertSame([], $this->events->events);
    }

    public function testItWorksWithoutAnEventDispatcher(): void
    {
        $reservation = $this->service(false)->book($this->table->id, $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'));
        $this->service(false)->cancel($reservation->id);

        self::assertSame([], $this->events->events);
    }
}
