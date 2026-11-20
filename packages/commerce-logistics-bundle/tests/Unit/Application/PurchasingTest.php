<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Application\Purchasing;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Exception\PurchaseRefused;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\PurchaseOrder;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseState;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Purchasing::class)]
#[CoversClass(PurchaseOrder::class)]
#[CoversClass(PurchaseRefused::class)]
final class PurchasingTest extends TestCase
{
    private InMemoryLogistics $repository;

    private Purchasing $purchasing;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse'));

        $this->purchasing = new Purchasing($this->repository, $this->repository, new Stock($this->repository, $this->repository));
        $this->at = new DateTimeImmutable('2026-11-12T10:00:00Z');
    }

    public function testGoodsAreReceivedAgainstTheirPurchaseOrderIntoTheJournal(): void
    {
        $order = $this->purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => 10, '1234' => 4], $this->at, new DateTimeImmutable('2026-11-20 15:00'));

        self::assertSame([PurchaseState::Open, '2026-11-20 00:00'], [$order->state(), $order->expectedOn()?->format('Y-m-d H:i')]);
        self::assertSame(14, $this->purchasing->onOrder('DICE') + $this->purchasing->onOrder('1234'));

        $this->purchasing->receive('PO-1', ['DICE' => 6], $this->at);
        self::assertSame([6, 4, PurchaseState::Open], [$order->received('DICE'), $this->purchasing->onOrder('DICE'), $order->state()]);

        $this->purchasing->receive('PO-1', ['DICE' => 4, '1234' => 4], $this->at);
        self::assertSame([PurchaseState::Received, 0, 0], [$order->state(), $this->purchasing->onOrder('DICE'), $order->outstanding('1234')]);
        self::assertSame(10, $this->repository->level('DICE', 'warehouse')?->onHand());
        self::assertSame(4, $this->repository->level('1234', 'warehouse')?->onHand());
        self::assertSame(
            [[MovementKind::Received, 6, 'PO-1'], [MovementKind::Received, 4, 'PO-1']],
            array_map(static fn (StockMovement $movement): array => [$movement->kind, $movement->onHandChange, $movement->reference], $this->repository->journal('DICE', 'warehouse')),
        );
    }

    public function testMoreThanIsStillExpectedIsRefused(): void
    {
        $this->purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => 2], $this->at);
        $this->purchasing->receive('PO-1', ['DICE' => 1], $this->at);

        $this->expectException(PurchaseRefused::class);
        $this->expectExceptionMessageIsOrContains('2 of DICE cannot be received against PO-1: 1 are still expected.');

        $this->purchasing->receive('PO-1', ['DICE' => 2], $this->at);
    }

    public function testASkuNotOrderedIsRefused(): void
    {
        $this->purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => 2], $this->at);

        $this->expectException(PurchaseRefused::class);
        $this->expectExceptionMessageIsOrContains('The purchase order PO-1 does not order MAPS.');

        $this->purchasing->receive('PO-1', ['MAPS' => 1], $this->at);
    }

    public function testAClosedOrderExpectsNothingMore(): void
    {
        $order = $this->purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => 2], $this->at);
        $this->purchasing->close('PO-1');

        self::assertSame([PurchaseState::Closed, 0, 0], [$order->state(), $order->outstanding('DICE'), $this->purchasing->onOrder('DICE')]);

        try {
            $this->purchasing->close('PO-1');
            self::fail('A closed order closed again.');
        } catch (PurchaseRefused $purchaseRefused) {
            self::assertSame('The purchase order PO-1 is no longer open.', $purchaseRefused->getMessage());
        }

        $this->expectException(PurchaseRefused::class);
        $this->expectExceptionMessageIsOrContains('The purchase order PO-1 is no longer open.');

        $order->receive('DICE', 1);
    }

    public function testAZeroReceiptIsRefused(): void
    {
        $order = $this->purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => 2], $this->at);

        $this->expectException(PurchaseRefused::class);
        $this->expectExceptionMessageIsOrContains('0 of DICE cannot be received against PO-1: 2 are still expected.');

        $order->receive('DICE', 0);
    }

    public function testAnOrderNumberIsUsedOnceAndAtAKnownLocation(): void
    {
        $this->purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => 2], $this->at);

        try {
            $this->purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => 2], $this->at);
            self::fail('A number was used twice.');
        } catch (PurchaseRefused $purchaseRefused) {
            self::assertSame('The purchase order PO-1 already exists.', $purchaseRefused->getMessage());
        }

        $this->expectException(StockRefused::class);
        $this->purchasing->order('PO-2', 'Dice Works', 'attic', ['DICE' => 2], $this->at);
    }

    public function testAnUnknownOrderIsRefused(): void
    {
        $this->expectException(PurchaseRefused::class);
        $this->expectExceptionMessageIsOrContains('No purchase order is known as "PO-9".');

        $this->purchasing->receive('PO-9', ['DICE' => 1], $this->at);
    }

    /**
     * @return iterable<string, array{string, string, array<string, int>, string}>
     */
    public static function invalidOrders(): iterable
    {
        yield 'a number with a space' => ['PO 1', 'Dice Works', ['DICE' => 1], 'is not a purchase order number'];
        yield 'a blank supplier' => ['PO-1', ' ', ['DICE' => 1], 'names its supplier in 1 to 255 characters'];
        yield 'a supplier too long' => ['PO-1', str_repeat('a', 256), ['DICE' => 1], 'names its supplier in 1 to 255 characters'];
        yield 'no line' => ['PO-1', 'Dice Works', [], 'lists 1 to 500 SKUs'];
        yield 'zero units' => ['PO-1', 'Dice Works', ['DICE' => 0], 'orders one unit or more of each SKU'];
        yield 'a SKU with a space' => ['PO-1', 'Dice Works', ['BAD SKU' => 1], 'orders one unit or more of each SKU'];
    }

    /**
     * @param array<string, int> $lines
     */
    #[DataProvider('invalidOrders')]
    public function testAnInvalidOrderIsRefused(string $number, string $supplier, array $lines, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        PurchaseOrder::of($number, $supplier, Location::of('warehouse', 'Warehouse'), $lines, $this->at);
    }

    public function testAnOrderListsAtMostFiveHundredSkus(): void
    {
        $lines = [];

        for ($sku = 1; $sku <= 500; ++$sku) {
            $lines['SKU-'.$sku] = 1;
        }

        self::assertCount(500, PurchaseOrder::of('PO-1', 'Dice Works', Location::of('warehouse', 'Warehouse'), $lines, $this->at)->ordered);

        $lines['SKU-501'] = 1;
        $this->expectException(InvalidValue::class);
        PurchaseOrder::of('PO-1', 'Dice Works', Location::of('warehouse', 'Warehouse'), $lines, $this->at);
    }

    public function testTheExpectedDayMoves(): void
    {
        $order = $this->purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => 2], $this->at);
        $order->expectOn(new DateTimeImmutable('2026-12-01 13:00'));
        self::assertSame('2026-12-01 00:00', $order->expectedOn()?->format('Y-m-d H:i'));

        $order->expectOn(null);
        self::assertNull($order->expectedOn());
    }
}
