<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLine;
use Phil\CommerceReturnsBundle\Infrastructure\Logistics\LogisticsRestocking;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryReturns;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryStock;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The resellable units of a product line back on the shelf of the logistics module: where they left from, or at the
 * configured location; nothing for another sellable or a SKU the stock never reserved for the order.
 */
#[CoversClass(LogisticsRestocking::class)]
final class LogisticsRestockingTest extends TestCase
{
    private InMemoryStock $levels;

    private InMemoryReturns $returns;

    private Stock $stock;

    private DateTimeImmutable $at;

    private string $return;

    #[Override]
    protected function setUp(): void
    {
        $this->at = new DateTimeImmutable('2026-11-21T10:00:00Z');
        $this->levels = new InMemoryStock();
        $this->levels->addLocation(Location::of('warehouse', 'Warehouse', 10));
        $this->levels->addLocation(Location::of('store', 'Store', 5));
        $this->levels->addLocation(Location::of('returns', 'Returns', 0));

        $this->returns = new InMemoryReturns();
        $this->stock = new Stock($this->levels, $this->levels);
        $this->stock->receive('DICE', 'warehouse', 1, $this->at);
        $this->stock->receive('DICE', 'store', 2, $this->at);
        $this->stock->receive('BOOK', 'store', 1, $this->at);

        $cad = Currency::of('CAD');
        $order = Order::open('A-1', $cad);

        foreach (['DICE' => 3, 'BOOK' => 1] as $sku => $quantity) {
            $order->add(Sellable::of(SellableReference::of('product', $sku), $sku, Money::of(1_000, $cad), 'goods'), $quantity, Money::of(1_000, $cad));
        }

        $order->place($this->at);
        $this->stock->reserve($order, $this->at);
        $this->stock->ship('A-1', $this->at);

        $return = ReturnRequest::request('A-1', $cad, [ReturnLine::of('product:DICE', 3, ReturnReason::ChangedMind, 3_000, 3)], 0, $this->at);
        $this->returns->addReturn($return);
        $this->return = $return->id;
    }

    public function testTheUnitsGoBackWhereTheyLeftFrom(): void
    {
        $restocking = new LogisticsRestocking($this->stock, $this->levels, $this->returns);
        $restocking->restock(SellableReference::of('product', 'DICE'), 1, $this->return, $this->at);
        $restocking->restock(SellableReference::of('product', 'DICE'), 1, $this->return, $this->at);

        // The warehouse held one: it came back first; the next goes to the store, where the rest left from.
        self::assertSame([['DICE', 'warehouse', 1, 'A-1'], ['DICE', 'store', 1, 'A-1']], $this->returned());
        self::assertSame([1, 1], [$this->levels->level('DICE', 'warehouse')?->onHand(), $this->levels->level('DICE', 'store')?->onHand()]);
    }

    public function testTheUnitsGoToTheConfiguredLocation(): void
    {
        new LogisticsRestocking($this->stock, $this->levels, $this->returns, 'returns')->restock(SellableReference::of('product', 'DICE', '0123456789abcdef'), 2, $this->return, $this->at);

        self::assertSame([['DICE', 'returns', 2, 'A-1']], $this->returned());
    }

    public function testOnceEveryUnitIsBackTheStockRefusesMore(): void
    {
        $restocking = new LogisticsRestocking($this->stock, $this->levels, $this->returns);
        $restocking->restock(SellableReference::of('product', 'BOOK'), 1, $this->return, $this->at);

        $this->expectException(StockRefused::class);
        $restocking->restock(SellableReference::of('product', 'BOOK'), 1, $this->return, $this->at);
    }

    public function testAnotherSellableOrAnUntrackedSkuPutsNothingBack(): void
    {
        $restocking = new LogisticsRestocking($this->stock, $this->levels, $this->returns, 'returns');
        $restocking->restock(SellableReference::of('offer', 'DICE'), 1, 'nowhere', $this->at);
        $restocking->restock(SellableReference::of('product', 'TEA'), 1, $this->return, $this->at);

        self::assertSame([], $this->returned());
    }

    public function testAnUnknownReturnIsRefused(): void
    {
        $this->expectExceptionObject(ReturnRefused::unknown('nowhere'));

        new LogisticsRestocking($this->stock, $this->levels, $this->returns)->restock(SellableReference::of('product', 'DICE'), 1, 'nowhere', $this->at);
    }

    /**
     * @return list<array{string, string, int, string|null}>
     */
    private function returned(): array
    {
        return array_values(array_map(
            static fn (StockMovement $movement): array => [$movement->sku, $movement->location, $movement->onHandChange, $movement->reference],
            array_filter($this->levels->movements, static fn (StockMovement $movement): bool => MovementKind::Returned === $movement->kind),
        ));
    }
}
