<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Unit;

use Phil\LocalPickup\ArrivalSource;
use Phil\LocalPickup\Exception\PickupRefused;
use Phil\LocalPickup\Exception\UnknownPickup;
use Phil\LocalPickup\Memory\InMemoryPickups;
use Phil\LocalPickup\Pickup;
use Phil\LocalPickup\PickupPoint;
use Phil\LocalPickup\PickupPointKind;
use Phil\LocalPickup\Pickups;
use Phil\LocalPickup\PickupSlots;
use Phil\LocalPickup\PickupState;
use Phil\LocalPickup\Tests\Support\Fixture;
use Phil\LocalPickup\Tests\Support\Recorder;
use Phil\Reservation\ViolationCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pickups::class)]
#[CoversClass(UnknownPickup::class)]
#[CoversClass(PickupRefused::class)]
final class PickupsTest extends TestCase
{
    private InMemoryPickups $store;

    private Recorder $recorder;

    private Pickups $pickups;

    protected function setUp(): void
    {
        $this->store = new InMemoryPickups();
        $this->recorder = new Recorder();
        $this->pickups = new Pickups($this->store, new PickupSlots($this->store), $this->recorder, $this->recorder);
    }

    public function testAPlacedPickupKeepsThePointAsAgreed(): void
    {
        $pickup = $this->place('Élise Tremblay');

        self::assertSame(PickupState::Preparing, $pickup->state);
        self::assertEquals(Fixture::point()->agreed(), $pickup->point);
        self::assertSame('Élise Tremblay', $pickup->collector);
        self::assertEquals($pickup, $this->store->find('WEB-1'));
        self::assertSame([], $this->recorder->dispatched);
    }

    public function testAPointThatChangesOrClosesChangesNothingForPlacedOrders(): void
    {
        $this->place();
        $moved = new PickupPoint('store-main', 'Main store (moved)', PickupPointKind::Store, 'other-room', 'Elsewhere', Fixture::point()->slots, holdHours: 1, accepting: false);

        try {
            $this->pickups->place('WEB-2', 'customer-8', $moved, Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00'));
            self::fail('A closed point takes no order.');
        } catch (PickupRefused $pickupRefused) {
            self::assertSame('point_closed', $pickupRefused->reason);
            self::assertSame('The pickup point "store-main" takes no new order.', $pickupRefused->getMessage());
        }

        $pickup = $this->pickups->ready('WEB-1', Fixture::at('2026-11-24 12:00'));

        self::assertSame('Main store', $pickup->point->name);
        self::assertSame('main-backroom', $pickup->point->stockLocation);
        self::assertSame(Fixture::at('2026-11-26 14:00')->getTimestamp(), $pickup->holdUntil?->getTimestamp());
        self::assertNull($this->store->find('WEB-2'));
    }

    public function testAnOrderHasOnePickup(): void
    {
        $this->place();

        try {
            $this->place();
            self::fail('An order has one pickup.');
        } catch (PickupRefused $pickupRefused) {
            self::assertSame('already_placed', $pickupRefused->reason);
        }
    }

    public function testARefusedSlotGivesItsViolationsAndKeepsNothing(): void
    {
        try {
            $this->pickups->place('WEB-1', 'customer-7', Fixture::point(), Fixture::slot('2026-11-24 10:00'), Fixture::at('2026-11-24 09:00'));
            self::fail('A slot within the preparation time is refused.');
        } catch (PickupRefused $pickupRefused) {
            self::assertSame('slot', $pickupRefused->reason);
            self::assertSame(ViolationCode::TooSoon, $pickupRefused->violations[0]->code);
            self::assertSame('The slot asked for the order "WEB-1" cannot be booked.', $pickupRefused->getMessage());
        }

        self::assertNull($this->store->find('WEB-1'));
    }

    public function testAReadyPickupIsSavedAndAnnouncedByItsOrderNumber(): void
    {
        $this->place();
        $pickup = $this->pickups->ready('WEB-1', Fixture::at('2026-11-24 12:00'));

        self::assertSame(PickupState::Ready, $pickup->state);
        self::assertSame(PickupState::Ready, $this->store->find('WEB-1')?->state);
        self::assertSame(['WEB-1'], $this->recorder->dispatched);
    }

    public function testResendingForgetsTheCodeAndAnnouncesAgain(): void
    {
        $this->place();
        $pickup = $this->pickups->ready('WEB-1', Fixture::at('2026-11-24 12:00'));
        $pickup->issueCode(str_repeat('a', 64));
        $pickup->failAttempt();
        $pickup->markNotified();

        $this->store->save($pickup);

        $this->pickups->resend('WEB-1');

        $read = $this->store->find('WEB-1');
        self::assertNotNull($read);
        self::assertNull($read->codeDigest);
        self::assertSame(0, $read->failedAttempts);
        self::assertFalse($read->notified);
        self::assertSame(['WEB-1', 'WEB-1'], $this->recorder->dispatched);
    }

    public function testTheStaffIsToldOfTheFirstArrivalOnly(): void
    {
        $this->place();

        self::assertTrue($this->pickups->arrive('WEB-1', ArrivalSource::Automatic, Fixture::at('2026-11-24 13:58')));
        self::assertFalse($this->pickups->arrive('WEB-1', ArrivalSource::Manual, Fixture::at('2026-11-24 14:00')));
        self::assertSame(['WEB-1'], $this->recorder->arrivals);
        self::assertSame(ArrivalSource::Automatic, $this->store->find('WEB-1')?->arrival);
    }

    public function testAnArrivalWorksWithoutAnArrivalNotifier(): void
    {
        $pickups = new Pickups($this->store, new PickupSlots($this->store), $this->recorder);
        $pickups->place('WEB-1', 'customer-7', Fixture::point(), Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00'));

        self::assertTrue($pickups->arrive('WEB-1', ArrivalSource::Manual, Fixture::at('2026-11-24 14:00')));
        self::assertSame(ArrivalSource::Manual, $this->store->find('WEB-1')?->arrival);
        self::assertSame([], $this->recorder->arrivals);
    }

    public function testAnUnknownOrderIsReported(): void
    {
        $this->expectException(UnknownPickup::class);
        $this->expectExceptionMessageIsOrContains('No pickup is kept for the order "WEB-9".');

        $this->pickups->get('WEB-9');
    }

    private function place(?string $collector = null): Pickup
    {
        return $this->pickups->place('WEB-1', 'customer-7', Fixture::point(), Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00'), $collector);
    }
}
