<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Unit;

use Phil\LocalPickup\Memory\InMemoryPickups;
use Phil\LocalPickup\Pickup;
use Phil\LocalPickup\PickupSlots;
use Phil\LocalPickup\Tests\Support\Fixture;
use Phil\Reservation\Interval;
use Phil\Reservation\ViolationCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PickupSlots::class)]
final class PickupSlotsTest extends TestCase
{
    public function testTheFirstSlotComesAfterThePreparationTime(): void
    {
        $slots = new PickupSlots(new InMemoryPickups())->available(Fixture::point(), $this->day(), Fixture::at('2026-11-24 09:10'));

        self::assertCount(13, $slots);
        self::assertSame(Fixture::at('2026-11-24 11:30')->getTimestamp(), $slots[0]->from);
        self::assertSame(Fixture::at('2026-11-24 12:00')->getTimestamp(), $slots[0]->until);
        self::assertSame(Fixture::at('2026-11-24 17:30')->getTimestamp(), $slots[12]->from);
    }

    public function testASlotStartingExactlyAfterThePreparationIsOffered(): void
    {
        $slots = new PickupSlots(new InMemoryPickups())->available(Fixture::point(), $this->day(), Fixture::at('2026-11-24 09:30'));

        self::assertSame(Fixture::at('2026-11-24 11:30')->getTimestamp(), $slots[0]->from);
    }

    public function testTheLimitIsKept(): void
    {
        self::assertCount(3, new PickupSlots(new InMemoryPickups())->available(Fixture::point(), $this->day(), Fixture::at('2026-11-24 06:00'), 3));
    }

    public function testAPeriodOverBeforeThePreparationEndsHasNoSlot(): void
    {
        $slots = new PickupSlots(new InMemoryPickups());

        self::assertSame([], $slots->available(Fixture::point(), $this->day(), Fixture::at('2026-11-24 22:00')));
        self::assertSame([], $slots->available(Fixture::point(), $this->day(), Fixture::at('2026-11-24 22:00')->modify('-2 hours')));
    }

    public function testAFullSlotIsNotOffered(): void
    {
        $pickups = new InMemoryPickups();
        $agreed = Fixture::point()->agreed();
        $pickups->save(Pickup::place('WEB-1', 'c-1', $agreed, Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 08:00')));
        $pickups->save(Pickup::place('WEB-2', 'c-2', $agreed, Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 08:00')));
        $pickups->save(Pickup::place('WEB-3', 'c-3', $agreed, Fixture::slot('2026-11-24 14:30'), Fixture::at('2026-11-24 08:00')));

        $slots = new PickupSlots($pickups)->available(Fixture::point(), Interval::ofTimestamps(Fixture::at('2026-11-24 13:30')->getTimestamp(), Fixture::at('2026-11-24 15:00')->getTimestamp(), Fixture::zone()), Fixture::at('2026-11-24 08:00'));

        self::assertSame([Fixture::at('2026-11-24 13:30')->getTimestamp(), Fixture::at('2026-11-24 14:30')->getTimestamp()], array_map(static fn (Interval $slot): int => $slot->from, $slots));
    }

    public function testTheBuffersOfThePointWidenTheBookingsRead(): void
    {
        $pickups = new InMemoryPickups();
        $point = Fixture::point(capacity: 1, bufferAfter: 30);
        $pickups->save(Pickup::place('WEB-1', 'c-1', $point->agreed(), Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 08:00')));
        $slots = new PickupSlots($pickups);

        self::assertFalse($slots->check($point, Fixture::slot('2026-11-24 14:30'), Fixture::at('2026-11-24 08:00'))->allowed());
        self::assertFalse($slots->check($point, Fixture::slot('2026-11-24 13:30'), Fixture::at('2026-11-24 08:00'))->allowed());
        self::assertTrue($slots->check($point, Fixture::slot('2026-11-24 15:00'), Fixture::at('2026-11-24 08:00'))->allowed());
        self::assertNotContains(Fixture::at('2026-11-24 14:30')->getTimestamp(), array_map(static fn (Interval $slot): int => $slot->from, $slots->available($point, $this->day(), Fixture::at('2026-11-24 08:00'))));
    }

    public function testASlotTooSoonIsRefusedWithThePreparationTime(): void
    {
        $decision = new PickupSlots(new InMemoryPickups())->check(Fixture::point(), Fixture::slot('2026-11-24 11:00'), Fixture::at('2026-11-24 09:01'));

        self::assertFalse($decision->allowed());
        self::assertTrue($decision->has(ViolationCode::TooSoon));
        self::assertSame(['minutes' => 120], $decision->violations[0]->parameters);
    }

    public function testASlotAtTheEndOfThePreparationIsAllowed(): void
    {
        self::assertTrue(new PickupSlots(new InMemoryPickups())->check(Fixture::point(), Fixture::slot('2026-11-24 11:00'), Fixture::at('2026-11-24 09:00'))->allowed());
    }

    public function testTheReasonsOfReservationAreKeptWithTheirConflicts(): void
    {
        $pickups = new InMemoryPickups();
        $point = Fixture::point(capacity: 1);
        $pickups->save(Pickup::place('WEB-1', 'c-1', $point->agreed(), Fixture::slot('2026-11-24 10:30'), Fixture::at('2026-11-24 08:00')));

        $decision = new PickupSlots($pickups)->check($point, Fixture::slot('2026-11-24 10:30'), Fixture::at('2026-11-24 09:30'));

        self::assertTrue($decision->has(ViolationCode::Full));
        self::assertTrue($decision->has(ViolationCode::TooSoon));
        self::assertSame(ViolationCode::Full, $decision->violations[0]->code);
        self::assertSame(['WEB-1'], $decision->conflicts);
    }

    private function day(): Interval
    {
        return Interval::ofTimestamps(Fixture::at('2026-11-24 00:00')->getTimestamp(), Fixture::at('2026-11-25 00:00')->getTimestamp(), Fixture::zone());
    }
}
