<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceConsignmentBundle\Application\ConfiguredIssueOrder;
use Phil\CommerceConsignmentBundle\Application\ShippedOrders;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;
use Phil\CommerceConsignmentBundle\Infrastructure\Logistics\LogisticsShippedUnits;
use Phil\CommerceConsignmentBundle\Infrastructure\Logistics\UnitsShippedListener;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryLedgerRepository;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\LogisticsMemory;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Event\ShippedLine;
use Phil\CommerceLogisticsBundle\Domain\Event\UnitsShipped;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(UnitsShippedListener::class)]
final class UnitsShippedListenerTest extends TestCase implements OrderRepository
{
    /** @var array<string, Order> */
    private array $orders = [];

    private InMemoryLedgerRepository $layers;

    private Ledger $ledger;

    private Stock $stock;

    private UnitsShippedListener $listener;

    protected function setUp(): void
    {
        $memory = new LogisticsMemory();
        $memory->addLocation(Location::of(Fixtures::SHELF, 'Store', 10));

        $this->layers = new InMemoryLedgerRepository();
        $this->ledger = new Ledger($this->layers);
        $this->listener = new UnitsShippedListener($this, new ShippedOrders($this->ledger, new LogisticsShippedUnits($memory), new ConfiguredIssueOrder(IssueOrder::ConsignedFirst)));

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(UnitsShipped::class, $this->listener);

        $this->stock = new Stock($memory, $memory, dispatcher: $dispatcher);
    }

    #[Override]
    public function find(string $number): ?Order
    {
        return $this->orders[$number] ?? null;
    }

    #[Override]
    public function add(Order $order): void
    {
        $this->orders[$order->number] = $order;
    }

    public function testEachShipmentSellsWhatLeftAtTheMomentItLeft(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        $this->stock->receive(Fixtures::BOOK, Fixtures::SHELF, 10, Fixtures::at(9));
        $order = Order::open('ORD-1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', Fixtures::BOOK), 'Book', Fixtures::cad(2000), 'goods'), 7, Fixtures::cad(2000));
        $order->place(Fixtures::at(10));
        $this->add($order);
        $this->stock->reserve($order, Fixtures::at(10));

        $this->stock->shipPart('ORD-1', Fixtures::BOOK, 2, Fixtures::at(11, 30));
        self::assertSame(2, $this->ledger->sold('ORD-1', Fixtures::BOOK));

        $this->stock->ship('ORD-1', Fixtures::at(12));
        self::assertSame(7, $this->ledger->sold('ORD-1', Fixtures::BOOK));
        self::assertSame(
            [[Fixtures::at(11, 30)->getTimestamp(), -2, Fixtures::AUTHOR], [Fixtures::at(12)->getTimestamp(), -3, Fixtures::AUTHOR], [Fixtures::at(12)->getTimestamp(), -2, '']],
            array_map(
                static fn (LedgerEntry $entry): array => [$entry->movedAt()->getTimestamp(), $entry->quantity, $entry->owner()->key()],
                array_values(array_filter($this->layers->entriesOf('ORD-1', Fixtures::BOOK), static fn (LedgerEntry $entry): bool => EntryKind::Sold === $entry->kind)),
            ),
        );
    }

    public function testAnOrderNoLongerThereIsSkipped(): void
    {
        ($this->listener)(new UnitsShipped('ORD-9', [new ShippedLine(Fixtures::BOOK, Fixtures::SHELF, 1)], Fixtures::at(11)));

        self::assertSame(0, $this->ledger->sold('ORD-9', Fixtures::BOOK));
    }
}
