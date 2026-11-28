<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\LocalPickup\Contract\PickupRepository;
use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\Expiry;
use Phil\LocalPickup\ExpiryOutcome;
use Phil\LocalPickup\Memory\InMemoryPickups;
use Phil\LocalPickup\Pickup;
use Phil\LocalPickup\PickupState;
use Phil\LocalPickup\Tests\Support\Fixture;
use Phil\LocalPickup\Tests\Support\Recorder;
use Phil\Reservation\Interval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Expiry::class)]
#[CoversClass(ExpiryOutcome::class)]
final class ExpiryTest extends TestCase
{
    public function testOverduePickupsAreCancelledAndRefunded(): void
    {
        $store = $this->store();
        $recorder = new Recorder();

        $expired = new Expiry($store, ExpiryOutcome::CancelAndRefund, $recorder)->run(Fixture::at('2026-11-26 14:30'));

        self::assertSame(['WEB-1'], $expired);
        self::assertSame(['WEB-1'], $recorder->cancelled);
        self::assertSame([], $recorder->restocked);
        self::assertSame(PickupState::Expired, $store->find('WEB-1')?->state);
        self::assertSame(PickupState::Ready, $store->find('WEB-2')?->state);
        self::assertSame(PickupState::Preparing, $store->find('WEB-3')?->state);
    }

    public function testOverduePickupsGoBackToTheAgreedStockLocation(): void
    {
        $store = $this->store();
        $recorder = new Recorder();

        $expired = new Expiry($store, ExpiryOutcome::Restock, restocker: $recorder)->run(Fixture::at('2026-11-27 18:00'));

        self::assertSame(['WEB-1', 'WEB-2'], $expired);
        self::assertSame(['WEB-1@main-backroom', 'WEB-2@main-backroom'], $recorder->restocked);
        self::assertSame([], $recorder->cancelled);
    }

    public function testARunReadsABoundedNumberOfPickups(): void
    {
        $recorder = new Recorder();

        self::assertSame(['WEB-1'], new Expiry($this->store(), ExpiryOutcome::Restock, $recorder, $recorder)->run(Fixture::at('2026-11-27 18:00'), 1));
        self::assertSame([], new Expiry(new InMemoryPickups(), ExpiryOutcome::Restock, $recorder, $recorder)->run(Fixture::at('2026-11-27 18:00'), Expiry::MAX_BATCH));
    }

    public function testTheBatchSizeIsBounded(): void
    {
        $recorder = new Recorder();
        $expiry = new Expiry(new InMemoryPickups(), ExpiryOutcome::Restock, $recorder, $recorder);

        foreach ([0, Expiry::MAX_BATCH + 1] as $limit) {
            try {
                $expiry->run(Fixture::at('2026-11-27 18:00'), $limit);
                self::fail('The batch size is bounded.');
            } catch (InvalidPickup $invalid) {
                self::assertSame('The batch size is between 1 and 1000.', $invalid->getMessage());
            }
        }
    }

    public function testAPickupNoLongerOverdueWhenReadIsLeft(): void
    {
        $store = $this->store();
        $stale = new readonly class($store) implements PickupRepository {
            public function __construct(private InMemoryPickups $inner)
            {
            }

            #[Override]
            public function find(string $order): ?Pickup
            {
                return $this->inner->find($order);
            }

            #[Override]
            public function save(Pickup $pickup): void
            {
                $this->inner->save($pickup);
            }

            #[Override]
            public function bookings(string $point, Interval $span): array
            {
                return [];
            }

            #[Override]
            public function overdue(DateTimeImmutable $at, int $limit): array
            {
                return ['WEB-2', 'WEB-3', 'WEB-9', 'WEB-1'];
            }

            #[Override]
            public function ofCustomer(string $customer): array
            {
                return [];
            }
        };
        $recorder = new Recorder();

        self::assertSame(['WEB-1'], new Expiry($stale, ExpiryOutcome::CancelAndRefund, $recorder)->run(Fixture::at('2026-11-26 14:30')));
        self::assertSame(['WEB-1'], $recorder->cancelled);
    }

    public function testTheConfiguredOutcomeNeedsItsPort(): void
    {
        $recorder = new Recorder();

        try {
            new Expiry(new InMemoryPickups(), ExpiryOutcome::CancelAndRefund, restocker: $recorder);
            self::fail('Cancelling needs a canceller.');
        } catch (InvalidPickup $invalidPickup) {
            self::assertSame('The expiry that cancels and refunds is given an ExpiredPickupCanceller.', $invalidPickup->getMessage());
        }

        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains('The expiry that restocks is given an ExpiredPickupRestocker.');

        new Expiry(new InMemoryPickups(), ExpiryOutcome::Restock, $recorder);
    }

    private function store(): InMemoryPickups
    {
        $store = new InMemoryPickups();
        $agreed = Fixture::point()->agreed();

        foreach (['WEB-1' => '2026-11-24 14:00', 'WEB-2' => '2026-11-25 15:00'] as $order => $slot) {
            $pickup = Pickup::place($order, 'customer-7', $agreed, Fixture::slot($slot), Fixture::at('2026-11-24 09:00'));
            $pickup->ready(Fixture::at('2026-11-24 12:00'));
            $store->save($pickup);
        }

        $store->save(Pickup::place('WEB-3', 'customer-7', $agreed, Fixture::slot('2026-11-24 15:00'), Fixture::at('2026-11-24 09:00')));

        return $store;
    }
}
