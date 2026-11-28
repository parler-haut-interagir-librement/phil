<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Functional;

use Phil\LocalPickup\ArrivalSource;
use Phil\LocalPickup\Counter;
use Phil\LocalPickup\Expiry;
use Phil\LocalPickup\ExpiryOutcome;
use Phil\LocalPickup\HandOverRequest;
use Phil\LocalPickup\Memory\InMemoryHandOverLog;
use Phil\LocalPickup\Memory\InMemoryPickups;
use Phil\LocalPickup\Message\NotifyPickupReady;
use Phil\LocalPickup\Message\NotifyPickupReadyHandler;
use Phil\LocalPickup\PickupCodes;
use Phil\LocalPickup\Pickups;
use Phil\LocalPickup\PickupSlots;
use Phil\LocalPickup\PickupState;
use Phil\LocalPickup\Tests\Support\Fixture;
use Phil\LocalPickup\Tests\Support\Recorder;
use Phil\Reservation\Interval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * An order collected at a store, another left until its hold period ends: every service on the same stores, as an
 * application wires them.
 */
#[CoversClass(Pickups::class)]
#[CoversClass(Counter::class)]
#[CoversClass(Expiry::class)]
#[CoversClass(NotifyPickupReadyHandler::class)]
final class PickupFlowTest extends TestCase
{
    public function testAnOrderIsPlacedAnnouncedAndCollectedWhileAnotherExpires(): void
    {
        $store = new InMemoryPickups();
        $log = new InMemoryHandOverLog();
        $recorder = new Recorder();
        $codes = new PickupCodes(Fixture::SECRET);
        $slots = new PickupSlots($store);
        $pickups = new Pickups($store, $slots, $recorder, $recorder);
        $worker = new NotifyPickupReadyHandler($store, $codes, $recorder);
        $counter = new Counter($store, $codes, $log);
        $point = Fixture::point();

        $offered = $slots->available($point, Interval::ofTimestamps(Fixture::at('2026-11-24 00:00')->getTimestamp(), Fixture::at('2026-11-25 00:00')->getTimestamp(), Fixture::zone()), Fixture::at('2026-11-24 09:00'));
        $pickups->place('WEB-1', 'customer-7', $point, $offered[0], Fixture::at('2026-11-24 09:00'), 'Élise Tremblay');
        $pickups->place('WEB-2', 'customer-8', $point, $offered[0], Fixture::at('2026-11-24 09:00'));

        self::assertNotEquals($offered[0], $slots->available($point, Interval::ofTimestamps(Fixture::at('2026-11-24 00:00')->getTimestamp(), Fixture::at('2026-11-25 00:00')->getTimestamp(), Fixture::zone()), Fixture::at('2026-11-24 09:00'))[0], 'The slot is full.');

        foreach (['WEB-1', 'WEB-2'] as $order) {
            $pickups->ready($order, Fixture::at('2026-11-24 10:45'));
        }

        foreach ($recorder->dispatched as $order) {
            $worker(new NotifyPickupReady($order));
        }

        self::assertCount(2, $recorder->notices);
        self::assertTrue($pickups->arrive('WEB-1', ArrivalSource::Manual, Fixture::at('2026-11-24 11:05')));

        $notice = $recorder->notices[0];
        $handOver = $counter->handOver(HandOverRequest::scanned($notice->code->payload(), 'booth-register', 'staff-3', Fixture::at('2026-11-24 11:10'), 'Élise Tremblay'));

        self::assertTrue($handOver->byThirdParty());
        self::assertSame(PickupState::PickedUp, $store->find('WEB-1')?->state);

        $expired = new Expiry($store, ExpiryOutcome::Restock, restocker: $recorder)->run(Fixture::at('2026-11-26 11:00'));

        self::assertSame(['WEB-2'], $expired);
        self::assertSame(['WEB-2@main-backroom'], $recorder->restocked);
        self::assertSame(PickupState::Expired, $store->find('WEB-2')?->state);
        self::assertCount(1, $log->all());
    }
}
