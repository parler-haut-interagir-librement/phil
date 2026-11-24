<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Infrastructure;

use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceConsignmentBundle\Application\ShippedQuantity;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Infrastructure\Logistics\LogisticsShippedUnits;
use Phil\CommerceConsignmentBundle\Infrastructure\Logistics\LogisticsStockMovements;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\LogisticsMemory;
use Phil\CommerceLogisticsBundle\Application\Purchasing;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Exception\PurchaseRefused;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseLine;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogisticsStockMovements::class)]
#[CoversClass(LogisticsShippedUnits::class)]
final class LogisticsAdaptersTest extends TestCase
{
    private LogisticsMemory $memory;

    private Stock $stock;

    private LogisticsStockMovements $movements;

    private Purchasing $purchasing;

    protected function setUp(): void
    {
        $this->memory = new LogisticsMemory();
        $this->memory->addLocation(Location::of(Fixtures::SHELF, 'Store', 10));
        $this->memory->addLocation(Location::of(Fixtures::BACK, 'Back room'));

        $this->stock = new Stock($this->memory, $this->memory);
        $this->purchasing = new Purchasing($this->memory, $this->memory, $this->stock);
        $this->movements = new LogisticsStockMovements($this->stock, $this->memory, $this->purchasing, $this->memory);
        $this->purchasing->order('PO-1', 'distributor', Fixtures::SHELF, [Fixtures::BOOK => 10], Fixtures::at(8));
    }

    public function testAReceiptWithoutPurchaseOrderAddsEachSkuOnce(): void
    {
        $this->movements->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        self::assertSame(10, $this->movements->onHand(Fixtures::BOOK, Fixtures::SHELF));
        self::assertSame([[10, 'DN-1']], array_map(static fn (StockMovement $movement): array => [$movement->onHandChange, $movement->reference], $this->memory->movements));
        self::assertSame(0, $this->memory->findPurchaseOrder('PO-1')?->received(Fixtures::BOOK));
    }

    public function testAReceiptAgainstAPurchaseOrderCountsTheUnitsAgainstIt(): void
    {
        $this->movements->receive(Receipt::of('DN-2', Fixtures::SHELF, [ReceiptLine::purchased(Fixtures::BOOK, 4, Fixtures::cad(1200))], 'PO-1'), Fixtures::at(10));

        self::assertSame(4, $this->memory->findPurchaseOrder('PO-1')?->received(Fixtures::BOOK));
        self::assertSame(4, $this->movements->onHand(Fixtures::BOOK, Fixtures::SHELF));
        self::assertSame('PO-1', $this->memory->movements[0]->reference);
    }

    public function testAReceiptAgainstAnOrderOfAnotherLocationOrNoneIsRefused(): void
    {
        try {
            $this->movements->receive(Receipt::of('DN-2', Fixtures::BACK, [ReceiptLine::purchased(Fixtures::BOOK, 4, Fixtures::cad(1200))], 'PO-1'), Fixtures::at(10));
            self::fail('The order comes to the store.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('The purchase order PO-1 is received at store, not at back-room.', $consignmentRefused->getMessage());
        }

        self::assertSame(0, $this->movements->onHand(Fixtures::BOOK, Fixtures::BACK));
        $this->expectException(PurchaseRefused::class);
        $this->movements->receive(Receipt::of('DN-2', Fixtures::SHELF, [ReceiptLine::purchased(Fixtures::BOOK, 4, Fixtures::cad(1200))], 'PO-9'), Fixtures::at(10));
    }

    public function testTransfersCountsAndReturnsMoveTheLevels(): void
    {
        $this->movements->receive(Fixtures::mixedReceipt(), Fixtures::at(10));

        $this->movements->transfer(Fixtures::BOOK, Fixtures::SHELF, Fixtures::BACK, 3, Fixtures::at(11), 'TR-1');
        $this->movements->adjust(Fixtures::BOOK, Fixtures::SHELF, -2, Fixtures::at(12), 'COUNT-1');
        $this->movements->adjust(Fixtures::BOOK, Fixtures::BACK, 1, Fixtures::at(12), null);
        $this->movements->restock(Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::at(13), 'ORD-1');

        self::assertSame(6, $this->movements->onHand(Fixtures::BOOK, Fixtures::SHELF));
        self::assertSame(4, $this->movements->onHand(Fixtures::BOOK, Fixtures::BACK));
        self::assertSame(0, $this->movements->onHand('PEN', Fixtures::SHELF));
        // The counts change the levels by their difference; a sale the logistics module never shipped comes back as a receipt.
        self::assertSame(
            [['received', 10, 'DN-1'], ['transferred_out', -3, 'TR-1'], ['transferred_in', 3, 'TR-1'], ['adjusted', -2, 'COUNT-1'], ['adjusted', 1, null], ['received', 1, 'ORD-1']],
            $this->journal(),
        );
    }

    public function testACountTakesOffWhatIsOnHandAtMost(): void
    {
        $this->stock->receive(Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::at(9));

        $this->movements->adjust(Fixtures::BOOK, Fixtures::SHELF, -3, Fixtures::at(12), 'COUNT-1');
        $this->movements->adjust(Fixtures::BOOK, Fixtures::SHELF, -1, Fixtures::at(13), 'COUNT-2');
        $this->movements->adjust('PEN', Fixtures::SHELF, -3, Fixtures::at(14), 'COUNT-3');

        self::assertSame(0, $this->movements->onHand(Fixtures::BOOK, Fixtures::SHELF));
        self::assertSame([['received', 2, null], ['adjusted', -2, 'COUNT-1']], $this->journal());
    }

    public function testUnitsGivenBackLeaveTheShelfByAnAdjustment(): void
    {
        $this->movements->receive(Fixtures::mixedReceipt(), Fixtures::at(10));
        $this->movements->giveBack(Fixtures::BOOK, Fixtures::SHELF, 4, Fixtures::at(11), 'RS-1');
        $this->movements->giveBack(Fixtures::BOOK, Fixtures::SHELF, 6, Fixtures::at(12), 'RS-2');

        self::assertSame(0, $this->movements->onHand(Fixtures::BOOK, Fixtures::SHELF));
        self::assertSame([['received', 10, 'DN-1'], ['adjusted', -4, 'RS-1'], ['adjusted', -6, 'RS-2']], $this->journal());

        $this->expectException(ConsignmentRefused::class);
        $this->expectExceptionMessageIsOrContains('The SKU BOOK-1 holds 0 unit(s) at store in the ledger; 1 were asked.');
        $this->movements->giveBack(Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::at(13), 'RS-3');
    }

    public function testAReturnOfAShippedOrderGoesBackAsACustomerReturn(): void
    {
        $this->stock->receive(Fixtures::BOOK, Fixtures::SHELF, 3, Fixtures::at(9));
        $this->stock->receive('PEN', Fixtures::SHELF, 3, Fixtures::at(9));

        $order = Order::open('ORD-1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'PEN'), 'Pen', Fixtures::cad(200), 'goods'), 1, Fixtures::cad(200));
        $order->add(Sellable::of(SellableReference::of('product', Fixtures::BOOK), 'Book', Fixtures::cad(2000), 'goods'), 2, Fixtures::cad(2000));
        $order->place(Fixtures::at(10));

        $this->stock->reserve($order, Fixtures::at(10));
        $this->stock->ship('ORD-1', Fixtures::at(11));

        $this->memory->movements = [];

        $this->movements->restock(Fixtures::BOOK, Fixtures::BACK, 2, Fixtures::at(12), 'ORD-1');

        self::assertSame(2, $this->movements->onHand(Fixtures::BOOK, Fixtures::BACK));
        self::assertSame([['returned', 2, 'ORD-1']], $this->journal());

        // A SKU the order never reserved is no customer return, whatever the other lines of the order are.
        $this->movements->restock('ZINE', Fixtures::BACK, 1, Fixtures::at(12), 'ORD-1');

        self::assertSame([['returned', 2, 'ORD-1'], ['received', 1, 'ORD-1']], $this->journal());

        $this->expectException(StockRefused::class);
        $this->movements->restock(Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::at(13), 'ORD-1');
    }

    public function testAReceiptAgreesWithTheOwnerAndTheUnitCostOfThePurchaseOrder(): void
    {
        $this->purchasing->order('PO-2', 'distributor', Fixtures::SHELF, [
            Fixtures::BOOK => PurchaseLine::of(4, Fixtures::cad(1200)),
            'ZINE' => PurchaseLine::of(3, owner: Fixtures::AUTHOR),
            'PEN' => 5,
            'CARD' => PurchaseLine::of(2, Fixtures::cad(50)),
        ], Fixtures::at(8));

        $this->movements->receive(Receipt::of('DN-2', Fixtures::SHELF, [
            ReceiptLine::purchased(Fixtures::BOOK, 4, Fixtures::cad(1200)),
            ReceiptLine::consigned('ZINE', 3, Fixtures::AUTHOR, ConsignmentTerms::share(Fixtures::cad(300))),
            ReceiptLine::consigned('PEN', 2, 'pen-maker', ConsignmentTerms::share(Fixtures::cad(100))),
            ReceiptLine::purchased('PEN', 3, Fixtures::cad(90)),
            // A consigned line has no unit cost to compare with the order's.
            ReceiptLine::consigned('CARD', 2, 'card-maker', ConsignmentTerms::share(Fixtures::cad(40))),
        ], 'PO-2'), Fixtures::at(10));

        self::assertSame([4, 3, 5, 2], array_map(fn (string $sku): int => $this->movements->onHand($sku, Fixtures::SHELF), [Fixtures::BOOK, 'ZINE', 'PEN', 'CARD']));
    }

    public function testAReceiptThatContradictsThePurchaseOrderIsRefusedBeforeAnythingMoves(): void
    {
        $this->purchasing->order('PO-2', 'distributor', Fixtures::SHELF, [
            Fixtures::BOOK => PurchaseLine::of(4, Fixtures::cad(1200)),
            'ZINE' => PurchaseLine::of(3, Fixtures::cad(0), Fixtures::AUTHOR),
        ], Fixtures::at(8));

        foreach ([
            'The purchase order PO-2 buys BOOK-1 at 12.00 CAD a unit; the receipt says 12.50 CAD.' => ReceiptLine::purchased(Fixtures::BOOK, 4, Fixtures::cad(1250)),
            'The purchase order PO-2 orders ZINE from the consignor author-ann; the receipt gives it to the shop.' => ReceiptLine::purchased('ZINE', 3, Fixtures::cad(0)),
            'The purchase order PO-2 orders ZINE from the consignor author-ann; the receipt gives it to the consignor author-bob.' => ReceiptLine::consigned('ZINE', 3, 'author-bob', ConsignmentTerms::share(Fixtures::cad(300))),
        ] as $message => $line) {
            try {
                $this->movements->receive(Receipt::of('DN-2', Fixtures::SHELF, [ReceiptLine::purchased(Fixtures::BOOK, 1, Fixtures::cad(1200)), $line], 'PO-2'), Fixtures::at(10));
                self::fail($message);
            } catch (ConsignmentRefused $consignmentRefused) {
                self::assertSame($message, $consignmentRefused->getMessage());
            }
        }

        self::assertSame([], $this->memory->movements);
        self::assertSame(0, $this->memory->findPurchaseOrder('PO-2')?->received(Fixtures::BOOK));
    }

    public function testTheShippedUnitsAreReadFromTheReservations(): void
    {
        $this->stock->receive(Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::at(9));
        $this->stock->receive(Fixtures::BOOK, Fixtures::BACK, 5, Fixtures::at(9));
        $this->stock->receive('PEN', Fixtures::SHELF, 5, Fixtures::at(9));

        $order = Order::open('ORD-1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', Fixtures::BOOK), 'Book', Fixtures::cad(2000), 'goods'), 4, Fixtures::cad(2000));
        $order->add(Sellable::of(SellableReference::of('product', 'PEN'), 'Pen', Fixtures::cad(200), 'goods'), 2, Fixtures::cad(200));
        $order->place(Fixtures::at(10));

        $this->stock->reserve($order, Fixtures::at(10));
        $shipped = new LogisticsShippedUnits($this->memory);

        self::assertSame([], $shipped->shipped('ORD-1'));

        $this->stock->shipPart('ORD-1', Fixtures::BOOK, 3, Fixtures::at(11));
        self::assertSame([[Fixtures::BOOK, Fixtures::SHELF, 2], [Fixtures::BOOK, Fixtures::BACK, 1]], $this->read($shipped->shipped('ORD-1')));

        $this->stock->ship('ORD-1', Fixtures::at(12));
        self::assertSame([[Fixtures::BOOK, Fixtures::SHELF, 2], [Fixtures::BOOK, Fixtures::BACK, 2], ['PEN', Fixtures::SHELF, 2]], $this->read($shipped->shipped('ORD-1')));
        self::assertSame([], $shipped->shipped('ORD-2'));
    }

    public function testTheShippedUnitsAreSummedBySkuAndLocationNeverMixed(): void
    {
        $this->memory->addLocation(Location::of('bc', 'Shelf BC'));
        $this->memory->addLocation(Location::of('c', 'Shelf C'));

        $this->stock->receive('A', 'bc', 3, Fixtures::at(9));
        $this->stock->receive('Ab', 'c', 3, Fixtures::at(9));

        $order = Order::open('ORD-1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'A'), 'A', Fixtures::cad(100), 'goods'), 1, Fixtures::cad(100));
        $order->add(Sellable::of(SellableReference::of('product', 'Ab'), 'Ab', Fixtures::cad(100), 'goods'), 2, Fixtures::cad(100));
        $order->add(Sellable::of(SellableReference::of('product', 'A'), 'A, signed', Fixtures::cad(150), 'goods', OptionSet::of(OptionDefinition::choice('dedication', 'Dedication', [OptionChoice::of('signed', 'Signed')]))), 1, Fixtures::cad(150), LineConfiguration::of(['dedication' => OptionValue::chosen('signed')]));
        $order->place(Fixtures::at(10));

        $this->stock->reserve($order, Fixtures::at(10));
        $this->stock->ship('ORD-1', Fixtures::at(11));

        self::assertEqualsCanonicalizing([['A', 'bc', 2], ['Ab', 'c', 2]], $this->read(new LogisticsShippedUnits($this->memory)->shipped('ORD-1')));
    }

    /**
     * @return list<array{string, int, ?string}> the kind, the change on hand and the reference of each movement
     */
    private function journal(): array
    {
        return array_map(static fn (StockMovement $movement): array => [$movement->kind->value, $movement->onHandChange, $movement->reference], $this->memory->movements);
    }

    /**
     * @param list<ShippedQuantity> $shipped
     *
     * @return list<array{string, string, int}>
     */
    private function read(array $shipped): array
    {
        return array_map(static fn (ShippedQuantity $quantity): array => [$quantity->sku, $quantity->location, $quantity->quantity], $shipped);
    }
}
