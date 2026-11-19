<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Domain;

use Closure;
use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\Domain\Stock\ReservationState;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Location::class)]
#[CoversClass(StockLevel::class)]
#[CoversClass(StockMovement::class)]
#[CoversClass(Reservation::class)]
#[CoversClass(StockRefused::class)]
final class StockTest extends TestCase
{
    public function testALocationIsOpenedClosedAndReopened(): void
    {
        $location = Location::of('montreal', 'Montréal', 5);

        self::assertSame('montreal', $location->code);
        self::assertSame('Montréal', $location->name);
        self::assertSame(5, $location->priority);
        self::assertTrue($location->isActive());
        self::assertSame(0, Location::of('a', 'A')->priority);

        $location->close();
        self::assertFalse($location->isActive());
        $location->reopen();
        self::assertTrue($location->isActive());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidLocations(): iterable
    {
        yield 'uppercase code' => ['Montreal', 'M', '"Montreal" is not a location code: up to 64 lowercase letters, digits, "_", "." or "-".'];
        yield 'code too long' => [str_repeat('a', 65), 'M', 'is not a location code'];
        yield 'code with a trailing newline' => ["mtl\n", 'M', 'is not a location code'];
        yield 'blank name' => ['mtl', ' ', 'The location mtl has a name.'];
    }

    #[DataProvider('invalidLocations')]
    public function testAnInvalidLocationIsRefused(string $code, string $name, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Location::of($code, $name);
    }

    public function testALevelCountsWhatIsOnHandAndReserved(): void
    {
        $level = $this->level();

        self::assertSame('DICE', $level->sku);
        self::assertSame('mtl', $level->location);
        self::assertSame([0, 0, 0], [$level->onHand(), $level->reserved(), $level->available()]);
        self::assertSame(1, $level->version());

        $level->receive(6);
        $level->receive(4);
        $level->reserve(4);
        self::assertSame([10, 4, 6], [$level->onHand(), $level->reserved(), $level->available()]);

        $level->release(1);
        self::assertSame([10, 3, 7], [$level->onHand(), $level->reserved(), $level->available()]);

        $level->ship(3);
        self::assertSame([7, 0, 7], [$level->onHand(), $level->reserved(), $level->available()]);

        $level->remove(7);
        self::assertSame([0, 0, 0], [$level->onHand(), $level->reserved(), $level->available()]);
    }

    /**
     * A count may find less on hand than is reserved: nothing is available, and the reservations stay.
     */
    public function testACountSetsWhatIsOnHand(): void
    {
        $level = $this->level();
        $level->receive(10);
        $level->reserve(6);

        self::assertSame(-6, $level->count(4));
        self::assertSame([4, 6, 0], [$level->onHand(), $level->reserved(), $level->available()]);
        self::assertSame(3, $level->count(7));
        self::assertSame(-7, $level->count(0));
    }

    public function testANegativeCountIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A count finds zero units or more; -1 is not.');

        $this->level()->count(-1);
    }

    /**
     * Releasing more than is reserved never goes below zero; shipping more than is on hand is refused, the level left
     * as it was.
     */
    public function testReleasingNeverGoesBelowZeroAndShippingTakesOnlyWhatIsOnHand(): void
    {
        $level = $this->level();
        $level->receive(2);
        $level->reserve(2);
        $level->count(1);

        try {
            $level->ship(2);
            self::fail('Two units left a shelf that holds one.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('2 of DICE cannot leave mtl: 1 are on hand.', $stockRefused->getMessage());
        }

        self::assertSame([1, 2], [$level->onHand(), $level->reserved()]);
        $level->ship(1);
        self::assertSame([0, 1], [$level->onHand(), $level->reserved()]);

        $level->release(5);
        self::assertSame([0, 0], [$level->onHand(), $level->reserved()]);
    }

    public function testABackorderReservesBeyondWhatIsAvailable(): void
    {
        $level = $this->level();
        $level->receive(2);
        $level->backorder(5);

        self::assertSame([2, 5, 0, 3], [$level->onHand(), $level->reserved(), $level->available(), $level->backordered()]);
        $level->receive(4);
        self::assertSame([1, 0], [$level->available(), $level->backordered()]);

        $this->expectException(StockRefused::class);
        $level->backorder(0);
    }

    public function testMoreThanIsAvailableIsNeitherReservedNorRemoved(): void
    {
        $level = $this->level();
        $level->receive(5);
        $level->reserve(3);

        foreach ([$level->reserve(...), $level->remove(...)] as $move) {
            try {
                $move(3);
                self::fail('The movement happened.');
            } catch (StockRefused $stockRefused) {
                self::assertSame('3 of DICE are asked at mtl; 2 are available.', $stockRefused->getMessage());
            }
        }

        $level->reserve(2);
        self::assertSame(0, $level->available());
    }

    /**
     * @return iterable<string, array{Closure(StockLevel): void}>
     */
    public static function moves(): iterable
    {
        yield 'receive' => [static fn (StockLevel $level) => $level->receive(0)];
        yield 'remove' => [static fn (StockLevel $level) => $level->remove(0)];
        yield 'reserve' => [static fn (StockLevel $level) => $level->reserve(0)];
        yield 'release' => [static fn (StockLevel $level) => $level->release(0)];
        yield 'ship' => [static fn (StockLevel $level) => $level->ship(0)];
    }

    /**
     * @param Closure(StockLevel): void $move
     */
    #[DataProvider('moves')]
    public function testAMovementMovesAtLeastOneUnit(Closure $move): void
    {
        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('A movement moves at least one unit; 0 does not.');

        $move($this->level());
    }

    public function testASkuIsVisibleAscii(): void
    {
        self::assertSame(64, \strlen(StockLevel::of(str_repeat('S', 64), Location::of('mtl', 'M'))->sku));

        foreach (['', 'A B', str_repeat('S', 65), "SKU\n"] as $sku) {
            try {
                StockLevel::of($sku, Location::of('mtl', 'M'));
                self::fail('The SKU was accepted.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame(\sprintf('"%s" is not a SKU: 1 to 64 visible ASCII characters.', $sku), $invalidValue->getMessage());
            }
        }
    }

    public function testAMovementRecordsWhatMovedWhereAndWhy(): void
    {
        $at = new DateTimeImmutable('2026-11-03T10:00:00Z');
        $movement = StockMovement::of($this->level(), MovementKind::Received, 12, 0, 'PO-1', $at);

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $movement->id);
        self::assertSame(['DICE', 'mtl', MovementKind::Received, 12, 0, 'PO-1', $at], [$movement->sku, $movement->location, $movement->kind, $movement->onHandChange, $movement->reservedChange, $movement->reference, $movement->movedAt]);
    }

    public function testAReservationIsHeldThenReleasedOrShipped(): void
    {
        $level = $this->level();
        $level->receive(10);

        $released = Reservation::hold('2026-0001', $level, 4);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $released->id);
        self::assertSame(['2026-0001', 'DICE', 'mtl', 4, ReservationState::Held], [$released->orderNumber, $released->sku, $released->location, $released->quantity, $released->state()]);
        self::assertSame(4, $level->reserved());

        $released->release($level);
        self::assertSame(ReservationState::Released, $released->state());
        self::assertSame([10, 0], [$level->onHand(), $level->reserved()]);

        $shipped = Reservation::hold('2026-0002', $level, 3);
        $shipped->ship($level);
        self::assertSame(ReservationState::Shipped, $shipped->state());
        self::assertSame([7, 0], [$level->onHand(), $level->reserved()]);

        foreach ([[$released, $released->ship(...)], [$shipped, $shipped->release(...)]] as [$reservation, $move]) {
            try {
                $move($level);
                self::fail('The reservation moved again.');
            } catch (StockRefused $stockRefused) {
                self::assertSame(\sprintf('The reservation %s is no longer held.', $reservation->id), $stockRefused->getMessage());
            }
        }

        self::assertSame([7, 0], [$level->onHand(), $level->reserved()]);
    }

    public function testRefusalsAreDomainErrors(): void
    {
        self::assertInstanceOf(DomainError::class, StockRefused::unknownLocation('x'));
        self::assertSame('No location is known as "x".', StockRefused::unknownLocation('x')->getMessage());
        self::assertSame('The order 1 lacks 2 of DICE in stock.', StockRefused::shortage('1', 'DICE', 2)->getMessage());
        self::assertSame('A transfer goes from one location to another, not from mtl to itself.', StockRefused::sameLocation('mtl')->getMessage());
    }

    private function level(): StockLevel
    {
        return StockLevel::of('DICE', Location::of('mtl', 'Montréal'));
    }
}
