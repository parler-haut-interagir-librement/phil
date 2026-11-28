<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Unit\Memory;

use Phil\LocalPickup\HandOver;
use Phil\LocalPickup\Memory\InMemoryHandOverLog;
use Phil\LocalPickup\Memory\InMemoryPickups;
use Phil\LocalPickup\Pickup;
use Phil\LocalPickup\Tests\Support\Fixture;
use Phil\Reservation\Booking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryPickups::class)]
#[CoversClass(InMemoryHandOverLog::class)]
final class InMemoryStoresTest extends TestCase
{
    public function testItKeepsWhatToArrayGivesAndRebuildsOnRead(): void
    {
        $store = new InMemoryPickups();
        $pickup = $this->pickup('WEB-1', '2026-11-24 14:00');
        $store->save($pickup);

        self::assertEquals($pickup, $store->find('WEB-1'));
        self::assertNotSame($pickup, $store->find('WEB-1'));
        self::assertNull($store->find('WEB-2'));
    }

    public function testBookingsAreThePickupsOfThePointThatHoldTheirSlot(): void
    {
        $store = new InMemoryPickups();
        $store->save($this->pickup('WEB-1', '2026-11-24 14:00'));
        $store->save($this->pickup('WEB-2', '2026-11-24 15:00'));
        $store->save($this->pickup('WEB-3', '2026-11-24 14:00', point: 'booth-1'));

        $picked = $this->pickup('WEB-4', '2026-11-24 14:00');
        $picked->ready(Fixture::at('2026-11-24 12:00'));
        $picked->pickUp(Fixture::at('2026-11-24 14:00'));

        $store->save($picked);
        $ready = $this->pickup('WEB-5', '2026-11-24 14:15');
        $ready->ready(Fixture::at('2026-11-24 12:00'));

        $store->save($ready);

        $bookings = $store->bookings('store-main', Fixture::slot('2026-11-24 14:00', 60));

        self::assertEquals([new Booking('WEB-1', Fixture::slot('2026-11-24 14:00')), new Booking('WEB-5', Fixture::slot('2026-11-24 14:15'))], $bookings);
        self::assertSame([], $store->bookings('store-main', Fixture::slot('2026-11-24 16:00')));
    }

    public function testOverdueOrdersComeOldestFirstWithinTheLimit(): void
    {
        $store = new InMemoryPickups();

        foreach (['WEB-3' => '2026-11-24 16:00', 'WEB-1' => '2026-11-24 14:00', 'WEB-2' => '2026-11-24 14:00', 'WEB-4' => '2026-11-26 14:00'] as $order => $slot) {
            $pickup = $this->pickup($order, $slot);
            $pickup->ready(Fixture::at('2026-11-24 12:00'));
            $store->save($pickup);
        }

        $store->save($this->pickup('WEB-5', '2026-11-24 10:00'));

        self::assertSame(['WEB-1', 'WEB-2', 'WEB-3'], $store->overdue(Fixture::at('2026-11-26 16:00'), 10));
        self::assertSame(['WEB-1', 'WEB-2'], $store->overdue(Fixture::at('2026-11-26 16:00'), 2));
        self::assertSame([], $store->overdue(Fixture::at('2026-11-26 13:00'), 10));
    }

    public function testThePickupsOfACustomerAreFound(): void
    {
        $store = new InMemoryPickups();
        $store->save($this->pickup('WEB-1', '2026-11-24 14:00'));
        $store->save($this->pickup('WEB-2', '2026-11-24 14:00', customer: 'customer-8'));
        $store->save($this->pickup('WEB-3', '2026-11-24 15:00'));

        self::assertSame(['WEB-1', 'WEB-3'], array_map(static fn (Pickup $pickup): string => $pickup->order, $store->ofCustomer('customer-7')));
    }

    public function testTheJournalKeepsEntriesInOrderAndErasesNames(): void
    {
        $log = new InMemoryHandOverLog();
        $first = new HandOver('WEB-1', 'customer-7', 'store-main', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05'), 'Sam', 'Élise');
        $second = new HandOver('WEB-2', 'customer-8', 'store-main', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:06'), 'Alex');
        $third = new HandOver('WEB-3', 'customer-7', 'store-main', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:07'));
        $log->record($first);
        $log->record($second);
        $log->record($third);

        self::assertSame([$first, $third], $log->ofCustomer('customer-7'));

        $log->eraseCollectors('customer-7');

        self::assertEquals([$first->withoutNames(), $second, $third], $log->all());
        self::assertSame($second, $log->all()[1]);
    }

    private function pickup(string $order, string $slot, string $customer = 'customer-7', string $point = 'store-main'): Pickup
    {
        return Pickup::place($order, $customer, Fixture::point(code: $point)->agreed(), Fixture::slot($slot), Fixture::at('2026-11-24 09:00'));
    }
}
