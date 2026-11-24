<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Application;

use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceConsignmentBundle\Application\ConfiguredIssueOrder;
use Phil\CommerceConsignmentBundle\Application\Contract\ShippedUnits;
use Phil\CommerceConsignmentBundle\Application\ShippedOrders;
use Phil\CommerceConsignmentBundle\Application\ShippedQuantity;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Sale;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryLedgerRepository;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShippedOrders::class)]
#[CoversClass(ShippedQuantity::class)]
final class ShippedOrdersTest extends TestCase implements ShippedUnits
{
    /** @var list<ShippedQuantity> */
    private array $shipped = [];

    private Ledger $ledger;

    private ShippedOrders $orders;

    protected function setUp(): void
    {
        $this->ledger = new Ledger(new InMemoryLedgerRepository());
        $this->orders = new ShippedOrders($this->ledger, $this, new ConfiguredIssueOrder(IssueOrder::ConsignedFirst));
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        $this->ledger->receive(Fixtures::purchased(4, 1000, 'DN-B', Fixtures::BACK), Fixtures::at(9));
    }

    #[Override]
    public function shipped(string $orderNumber): array
    {
        return 'ORD-1' === $orderNumber ? $this->shipped : [];
    }

    public function testAShipmentIsSoldAtThePriceOfTheOrderLinesOnce(): void
    {
        $order = $this->order([[Fixtures::BOOK, 3, 2000]]);
        $this->shipped = [new ShippedQuantity(Fixtures::BOOK, Fixtures::SHELF, 3)];

        $sales = $this->orders->record($order, Fixtures::at(11));

        self::assertCount(1, $sales);
        self::assertSame([3, 2000], [$sales[0]->quantityOf(Owner::consignor(Fixtures::AUTHOR)), $sales[0]->lines[0]->margin->revenue->minorUnits / 3]);
        self::assertSame('ORD-1', $sales[0]->reference);
        self::assertSame([], $this->orders->record($order, Fixtures::at(12)));
        self::assertSame(3, $this->ledger->sold('ORD-1', Fixtures::BOOK));
    }

    public function testAPartialShipmentWritesOnlyWhatLeftSince(): void
    {
        $order = $this->order([[Fixtures::BOOK, 5, 2000]]);
        $this->shipped = [new ShippedQuantity(Fixtures::BOOK, Fixtures::SHELF, 2)];
        $this->orders->record($order, Fixtures::at(11));

        $this->shipped = [new ShippedQuantity(Fixtures::BOOK, Fixtures::SHELF, 3), new ShippedQuantity(Fixtures::BOOK, Fixtures::BACK, 2)];
        $sales = $this->orders->record($order, Fixtures::at(12));

        self::assertSame([1, 2], array_map(static fn (Sale $sale): int => $sale->quantity(), $sales));
        self::assertSame(Fixtures::BACK, $sales[1]->lines[0]->location);
        self::assertSame(5, $this->ledger->sold('ORD-1', Fixtures::BOOK));
    }

    public function testALocationWrittenAlreadyLeavesTheOthersToWrite(): void
    {
        $order = $this->order([['PEN', 1, 100], [Fixtures::BOOK, 4, 2000]]);
        $this->shipped = [new ShippedQuantity(Fixtures::BOOK, Fixtures::SHELF, 2)];
        $this->orders->record($order, Fixtures::at(11));

        $this->shipped = [new ShippedQuantity(Fixtures::BOOK, Fixtures::SHELF, 2), new ShippedQuantity(Fixtures::BOOK, Fixtures::BACK, 2)];
        $sales = $this->orders->record($order, Fixtures::at(12));

        self::assertSame([[Fixtures::BACK, 2]], array_map(static fn (Sale $sale): array => [$sale->lines[0]->location, $sale->quantity()], $sales));
        self::assertSame(4, $this->ledger->sold('ORD-1', Fixtures::BOOK));
    }

    public function testTwoLinesOfOneSkuAreSoldEachAtItsPrice(): void
    {
        $order = Order::open('ORD-1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', Fixtures::BOOK), 'Book', Fixtures::cad(2000), 'goods'), 2, Fixtures::cad(2000));

        $signed = OptionSet::of(OptionDefinition::choice('dedication', 'Dedication', [OptionChoice::of('signed', 'Signed')]));
        $order->add(Sellable::of(SellableReference::of('product', Fixtures::BOOK), 'Signed book', Fixtures::cad(3000), 'goods', $signed), 2, Fixtures::cad(3000), LineConfiguration::of(['dedication' => OptionValue::chosen('signed')]));
        $order->add(Sellable::of(SellableReference::of('gift_card', 'GC-1'), 'Gift card', Fixtures::cad(5000), 'exempt'), 1, Fixtures::cad(5000));

        $this->shipped = [new ShippedQuantity(Fixtures::BOOK, Fixtures::SHELF, 1)];
        $this->orders->record($order, Fixtures::at(11));

        $this->shipped = [new ShippedQuantity(Fixtures::BOOK, Fixtures::SHELF, 4)];
        $sales = $this->orders->record($order, Fixtures::at(12));

        self::assertSame([[1, 2000], [2, 3000]], array_map(static fn (Sale $sale): array => [$sale->quantity(), intdiv((int) $sale->margin()?->revenue->minorUnits, $sale->quantity())], $sales));
    }

    public function testMoreShippedThanOrderedIsRefused(): void
    {
        $order = $this->order([[Fixtures::BOOK, 2, 2000]]);
        $this->shipped = [new ShippedQuantity(Fixtures::BOOK, Fixtures::SHELF, 3)];

        try {
            $this->orders->record($order, Fixtures::at(11));
            self::fail('The order holds two units.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('The order ORD-1 shipped 3 unit(s) of BOOK-1 and holds 2.', $consignmentRefused->getMessage());
        }

        $this->shipped = [new ShippedQuantity('PEN', Fixtures::SHELF, 1)];
        $this->expectExceptionMessageIsOrContains('The order ORD-1 shipped 1 unit(s) of PEN and holds 0.');
        $this->orders->record($order, Fixtures::at(11));
    }

    public function testAnOrderThatShippedNothingWritesNothing(): void
    {
        self::assertSame([], $this->orders->record($this->order([[Fixtures::BOOK, 2, 2000]], 'ORD-2'), Fixtures::at(11)));
    }

    /**
     * @param list<array{string, int, int}> $lines SKU, units, unit price in cents
     */
    private function order(array $lines, string $number = 'ORD-1'): Order
    {
        $order = Order::open($number, Currency::of('CAD'));

        foreach ($lines as [$sku, $quantity, $price]) {
            $order->add(Sellable::of(SellableReference::of('product', $sku), $sku, Fixtures::cad($price), 'goods'), $quantity, Fixtures::cad($price));
        }

        return $order;
    }
}
