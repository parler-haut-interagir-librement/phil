<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Benchmark;

use LogicException;
use Phil\LocalPickup\IssuedCode;
use Phil\LocalPickup\Memory\InMemoryPickups;
use Phil\LocalPickup\Pickup;
use Phil\LocalPickup\PickupCodes;
use Phil\LocalPickup\PickupPoint;
use Phil\LocalPickup\PickupSlots;
use Phil\LocalPickup\Tests\Support\Fixture;
use Phil\Reservation\Interval;

/**
 * The hot paths of a busy booth: the slots of a week offered while 400 orders are booked, the code checked at the
 * counter, and a stored pickup read back.
 */
final readonly class PickupBench
{
    private PickupSlots $slots;

    private PickupPoint $point;

    private Interval $week;

    private PickupCodes $codes;

    private IssuedCode $issued;

    /** @var array<string, mixed> */
    private array $row;

    public function __construct()
    {
        $store = new InMemoryPickups();
        $this->point = Fixture::point(capacity: 20);
        $agreed = $this->point->agreed();

        for ($i = 0; $i < 400; ++$i) {
            $start = Fixture::at('2026-11-24 10:00')->modify(\sprintf('+%d days +%d minutes', intdiv($i, 80), 30 * ($i % 16)));
            $store->save(Pickup::place('WEB-'.$i, 'customer-'.$i, $agreed, new Interval($start, $start->modify('+30 minutes')), Fixture::at('2026-11-20 09:00')));
        }

        $this->slots = new PickupSlots($store);
        $this->week = Interval::ofTimestamps(Fixture::at('2026-11-24 00:00')->getTimestamp(), Fixture::at('2026-12-01 00:00')->getTimestamp(), Fixture::zone());
        $this->codes = new PickupCodes(Fixture::SECRET);
        $this->issued = $this->codes->issue('WEB-1');
        $this->row = $store->find('WEB-1')?->toArray() ?? throw new LogicException('The pickup is stored.');
    }

    public function benchSlotsOfAWeek(): void
    {
        if ([] === $this->slots->available($this->point, $this->week, Fixture::at('2026-11-23 09:00'), 200)) {
            throw new LogicException('The week has slots.');
        }
    }

    public function benchCodeCheckedAtTheCounter(): void
    {
        if (!$this->codes->matches('WEB-1', $this->issued->display(), $this->issued->digest)) {
            throw new LogicException('The code matches.');
        }
    }

    public function benchStoredPickupReadBack(): void
    {
        if ('WEB-1' !== Pickup::fromArray($this->row)->order) {
            throw new LogicException('The pickup is read back.');
        }
    }
}
