<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Unit;

use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\HandOver;
use Phil\LocalPickup\Memory\InMemoryHandOverLog;
use Phil\LocalPickup\Memory\InMemoryPickups;
use Phil\LocalPickup\Pickup;
use Phil\LocalPickup\PickupPersonalData;
use Phil\LocalPickup\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PickupPersonalData::class)]
final class PickupPersonalDataTest extends TestCase
{
    public function testACustomerWithoutPickupsExportsNothing(): void
    {
        self::assertSame([], new PickupPersonalData(new InMemoryPickups(), new InMemoryHandOverLog())->export('customer-7'));
    }

    public function testItExportsThePickupsAndHandOversOfTheCustomer(): void
    {
        [$pickups, $log] = $this->stores();

        self::assertSame([
            'pickups' => [
                ['order' => 'WEB-1', 'point' => 'Main store', 'slot' => '2026-11-24T14:00:00-05:00', 'state' => 'preparing', 'collector' => 'Élise Tremblay'],
                ['order' => 'WEB-3', 'point' => 'Main store', 'slot' => '2026-11-24T15:00:00-05:00', 'state' => 'preparing', 'collector' => null],
            ],
            'hand_overs' => [
                ['order' => 'WEB-0', 'at' => '2026-11-20T14:05:00-05:00', 'collector' => 'Sam'],
            ],
        ], new PickupPersonalData($pickups, $log)->export('customer-7'));
    }

    public function testItErasesTheCollectorsNamesAndKeepsTheRest(): void
    {
        [$pickups, $log] = $this->stores();
        $data = new PickupPersonalData($pickups, $log);

        self::assertSame(['pickups' => 'redacted', 'pickup_hand_overs' => 'redacted'], $data->erase('customer-7'));
        self::assertSame(['pickups' => 'redacted', 'pickup_hand_overs' => 'redacted'], $data->erase('customer-7'), 'An erasure runs again.');

        self::assertNull($pickups->find('WEB-1')?->collector);
        self::assertSame('Alex', $pickups->find('WEB-2')?->collector);
        self::assertNull($log->all()[0]->collector);
        self::assertNull($log->all()[0]->designated);
        self::assertSame('WEB-0', $log->all()[0]->order);
        self::assertSame('Alex', $log->all()[1]->collector);
    }

    public function testItRefusesACustomerOutOfShape(): void
    {
        $data = new PickupPersonalData(new InMemoryPickups(), new InMemoryHandOverLog());

        try {
            $data->export('');
            self::fail('An empty customer is refused.');
        } catch (InvalidPickup) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidPickup::class);

        $data->erase("customer\n");
    }

    /**
     * @return array{InMemoryPickups, InMemoryHandOverLog}
     */
    private function stores(): array
    {
        $pickups = new InMemoryPickups();
        $agreed = Fixture::point()->agreed();
        $pickups->save(Pickup::place('WEB-1', 'customer-7', $agreed, Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00'), 'Élise Tremblay'));
        $pickups->save(Pickup::place('WEB-2', 'customer-8', $agreed, Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00'), 'Alex'));
        $pickups->save(Pickup::place('WEB-3', 'customer-7', $agreed, Fixture::slot('2026-11-24 15:00'), Fixture::at('2026-11-24 09:00')));

        $log = new InMemoryHandOverLog();
        $log->record(new HandOver('WEB-0', 'customer-7', 'store-main', 'counter-1', 'staff-3', Fixture::at('2026-11-20 14:05'), 'Sam', 'Sam'));
        $log->record(new HandOver('WEB-9', 'customer-8', 'store-main', 'counter-1', 'staff-3', Fixture::at('2026-11-20 14:05'), 'Alex'));

        return [$pickups, $log];
    }
}
