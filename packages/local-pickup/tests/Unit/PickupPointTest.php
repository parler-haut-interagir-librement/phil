<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Unit;

use Phil\LocalPickup\AgreedPickupPoint;
use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\PickupPoint;
use Phil\LocalPickup\PickupPointKind;
use Phil\LocalPickup\Tests\Support\Fixture;
use Phil\Reservation\Bookable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PickupPoint::class)]
#[CoversClass(AgreedPickupPoint::class)]
final class PickupPointTest extends TestCase
{
    public function testItHasDefaultsForItsSlotsAndHold(): void
    {
        $point = new PickupPoint('booth-12', 'Booth 12', PickupPointKind::ConventionBooth, 'convention-stock', 'Hall B', new Bookable());

        self::assertSame(30, $point->slotMinutes);
        self::assertSame(120, $point->preparationMinutes);
        self::assertSame(168, $point->holdHours);
        self::assertTrue($point->accepting);
    }

    public function testTheAgreedCopyKeepsWhatTheCustomerAgreedTo(): void
    {
        $agreed = Fixture::point()->agreed();

        self::assertSame('store-main', $agreed->code);
        self::assertSame('Main store', $agreed->name);
        self::assertSame(PickupPointKind::Store, $agreed->kind);
        self::assertSame('main-backroom', $agreed->stockLocation);
        self::assertSame("12 Rue Principale\nMontréal", $agreed->address);
        self::assertSame('America/Toronto', $agreed->timezone);
        self::assertSame('America/Toronto', $agreed->zone()->getName());
        self::assertSame(48, $agreed->holdHours);
    }

    public function testTheAgreedCopyIsReadBackFromItsArray(): void
    {
        $agreed = Fixture::point()->agreed();

        self::assertSame([
            'code' => 'store-main',
            'name' => 'Main store',
            'kind' => 'store',
            'stock_location' => 'main-backroom',
            'address' => "12 Rue Principale\nMontréal",
            'timezone' => 'America/Toronto',
            'hold_hours' => 48,
        ], $agreed->toArray());
        self::assertEquals($agreed, AgreedPickupPoint::fromArray($agreed->toArray()));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badRows(): iterable
    {
        $row = Fixture::point()->agreed()->toArray();

        yield 'unknown kind' => [['kind' => 'van'] + $row, 'The pickup point kind is "store" or "convention_booth".'];
        yield 'kind missing' => [array_diff_key($row, ['kind' => true]), 'The field "kind" of the pickup point is a string.'];
        yield 'hold as text' => [['hold_hours' => '48'] + $row, 'The hold period is an integer.'];
        yield 'hold too long' => [['hold_hours' => PickupPoint::MAX_HOLD_HOURS + 1] + $row, 'The hold period is between 1 and 2160.'];
        yield 'name missing' => [array_diff_key($row, ['name' => true]), 'The field "name" of the pickup point is a string.'];
        yield 'unknown zone' => [['timezone' => 'Mars/Olympus'] + $row, 'The time zone of the pickup point is a time zone identifier.'];
        yield 'bad stock location' => [['stock_location' => 'Back Room'] + $row, 'The stock location is up to 64'];
        yield 'bad address' => [['address' => ''] + $row, 'The pickup point address is a text'];
        yield 'bad name' => [['name' => "a\nb"] + $row, 'The pickup point name is a text'];
        yield 'bad code' => [['code' => 'A'] + $row, 'The pickup point code is up to 64'];
    }

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('badRows')]
    public function testItRefusesARowOutOfShape(array $row, string $message): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains($message);

        AgreedPickupPoint::fromArray($row);
    }

    /**
     * @return iterable<string, array{callable(): PickupPoint, string}>
     */
    public static function badPoints(): iterable
    {
        $bookable = new Bookable();

        yield 'code' => [static fn (): PickupPoint => new PickupPoint('Main', 'Main', PickupPointKind::Store, 'stock', 'Here', $bookable), 'pickup point code'];
        yield 'name' => [static fn (): PickupPoint => new PickupPoint('main', ' ', PickupPointKind::Store, 'stock', 'Here', $bookable), 'pickup point name'];
        yield 'stock location' => [static fn (): PickupPoint => new PickupPoint('main', 'Main', PickupPointKind::Store, 'Stock', 'Here', $bookable), 'stock location'];
        yield 'address' => [static fn (): PickupPoint => new PickupPoint('main', 'Main', PickupPointKind::Store, 'stock', "a\tb", $bookable), 'pickup point address'];
        yield 'slot too short' => [static fn (): PickupPoint => new PickupPoint('main', 'Main', PickupPointKind::Store, 'stock', 'Here', $bookable, 0), 'slot length is between 1 and 1440'];
        yield 'slot too long' => [static fn (): PickupPoint => new PickupPoint('main', 'Main', PickupPointKind::Store, 'stock', 'Here', $bookable, 1_441), 'slot length'];
        yield 'negative preparation' => [static fn (): PickupPoint => new PickupPoint('main', 'Main', PickupPointKind::Store, 'stock', 'Here', $bookable, 30, -1), 'preparation time is between 0 and 43200'];
        yield 'long preparation' => [static fn (): PickupPoint => new PickupPoint('main', 'Main', PickupPointKind::Store, 'stock', 'Here', $bookable, 30, 43_201), 'preparation time'];
        yield 'no hold' => [static fn (): PickupPoint => new PickupPoint('main', 'Main', PickupPointKind::Store, 'stock', 'Here', $bookable, 30, 0, 0), 'hold period is between 1 and 2160'];
        yield 'long hold' => [static fn (): PickupPoint => new PickupPoint('main', 'Main', PickupPointKind::Store, 'stock', 'Here', $bookable, 30, 0, 2_161), 'hold period'];
    }

    /**
     * @param callable(): PickupPoint $build
     */
    #[DataProvider('badPoints')]
    public function testItRefusesASettingOutOfBounds(callable $build, string $field): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains($field);

        $build();
    }

    public function testItAcceptsTheBoundsOfItsSettings(): void
    {
        $point = new PickupPoint('main', 'Main', PickupPointKind::Store, 'stock', 'Here', new Bookable(), 1_440, 43_200, 2_160);
        $shortest = new PickupPoint('main', 'Main', PickupPointKind::Store, 'stock', 'Here', new Bookable(), 1, 0, 1);

        self::assertSame(2_160, $point->agreed()->holdHours);
        self::assertSame(1, $shortest->agreed()->holdHours);
    }
}
