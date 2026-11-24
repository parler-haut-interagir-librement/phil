<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceConsignmentBundle\Application\ConsignmentStock;
use Phil\CommerceConsignmentBundle\Application\ConsignorAgreements;
use Phil\CommerceConsignmentBundle\Application\ConsignorPayouts;
use Phil\CommerceConsignmentBundle\Application\ConsignorStatements;
use Phil\CommerceConsignmentBundle\Application\Export\StatementCsv;
use Phil\CommerceConsignmentBundle\Application\Message\RecordShipment;
use Phil\CommerceConsignmentBundle\Application\ShippedOrders;
use Phil\CommerceConsignmentBundle\Application\UnsoldStock;
use Phil\CommerceConsignmentBundle\Domain\Consignor\PayoutState;
use Phil\CommerceConsignmentBundle\Domain\Consignor\StatementFrequency;
use Phil\CommerceConsignmentBundle\Domain\Consignor\UnsoldPolicy;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\OwnerQuantity;
use Phil\CommerceConsignmentBundle\Infrastructure\Logistics\LogisticsStockMovements;
use Phil\CommerceConsignmentBundle\Infrastructure\Logistics\UnitsShippedListener;
use Phil\CommerceConsignmentBundle\Infrastructure\Messenger\RecordShipmentHandler;
use Phil\CommerceConsignmentBundle\PhilCommerceConsignmentBundle;
use Phil\CommerceConsignmentBundle\Tests\Functional\App\TestKernel;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Repository\LocationRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Goods received bought and consigned, an order reserved and shipped by the logistics module, and its sale written to
 * the ledger when the logistics module announces the shipment, through the container; everything read back from a fresh
 * unit of work.
 */
#[CoversClass(PhilCommerceConsignmentBundle::class)]
#[CoversClass(RecordShipmentHandler::class)]
#[CoversClass(UnitsShippedListener::class)]
#[CoversClass(LogisticsStockMovements::class)]
#[CoversClass(UnsoldStock::class)]
#[CoversClass(ConsignorStatements::class)]
#[CoversClass(ConsignorPayouts::class)]
final class ConsignmentFlowTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        // Symfony registers the event dispatcher only when a package of production requires it: installed alone, the
        // bundle has it for development only, and no kernel can boot.
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->entityManager();
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $locations = $this->service(LocationRepository::class);
        $locations->addLocation(Location::of(Fixtures::SHELF, 'Store', 10));
        $locations->addLocation(Location::of(Fixtures::BACK, 'Back room'));

        $entityManager->flush();
    }

    public function testAShippedOrderSellsTheConsignedCopiesFirst(): void
    {
        $this->service(ConsignmentStock::class)->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        $this->entityManager()->flush();

        $order = Order::open('2026-0001', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', Fixtures::BOOK), 'Book', Fixtures::cad(2000), 'goods'), 6, Fixtures::cad(2000));
        $order->place(Fixtures::at(10));
        $this->service(OrderRepository::class)->add($order);
        $stock = $this->service(Stock::class);
        $stock->reserve($order, Fixtures::at(10));
        $this->entityManager()->flush();
        // The logistics module announces the shipment: its sales join the same flush.
        $stock->ship('2026-0001', Fixtures::at(11));
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $consignment = $this->service(ConsignmentStock::class);
        self::assertSame([['', 4]], array_map(static fn (OwnerQuantity $split): array => [$split->owner->key(), $split->quantity], $consignment->split(Fixtures::BOOK)));
        self::assertSame(4, $this->service(StockRepository::class)->level(Fixtures::BOOK, Fixtures::SHELF)?->onHand());

        // A message for the same order writes nothing twice; one for an order no longer there is skipped.
        $this->service(MessageBusInterface::class)->dispatch(new RecordShipment('2026-0001'));
        $this->service(MessageBusInterface::class)->dispatch(new RecordShipment('2026-0002'));
        $this->entityManager()->flush();
        $this->entityManager()->clear();
        self::assertSame([['', 4]], array_map(static fn (OwnerQuantity $split): array => [$split->owner->key(), $split->quantity], $consignment->split(Fixtures::BOOK)));

        $order = $this->service(OrderRepository::class)->find('2026-0001');
        self::assertInstanceOf(Order::class, $order);
        self::assertSame([], $this->service(ShippedOrders::class)->record($order, Fixtures::at(12)));

        $consignment->returnSold('2026-0001', Fixtures::BOOK, Fixtures::BACK, 1, Fixtures::at(13));
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        // The units sold last come back first: the shop's copy, which left after the author's five.
        self::assertSame([['', 1]], array_map(static fn (OwnerQuantity $split): array => [$split->owner->key(), $split->quantity], $consignment->split(Fixtures::BOOK, Fixtures::BACK)));
        self::assertSame(1, $this->service(StockRepository::class)->level(Fixtures::BOOK, Fixtures::BACK)?->onHand());
        self::assertSame([MovementKind::Returned], array_map(static fn (StockMovement $movement): MovementKind => $movement->kind, $this->service(StockRepository::class)->journal(Fixtures::BOOK, Fixtures::BACK)));
    }

    public function testAPartialShipmentAndAnotherInTheSameUnitOfWorkSellEachUnitOnce(): void
    {
        $this->service(ConsignmentStock::class)->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        $this->entityManager()->flush();
        $order = Order::open('2026-0003', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', Fixtures::BOOK), 'Book', Fixtures::cad(2000), 'goods'), 7, Fixtures::cad(2000));
        $order->place(Fixtures::at(10));
        $this->service(OrderRepository::class)->add($order);
        $stock = $this->service(Stock::class);
        $stock->reserve($order, Fixtures::at(10));
        $this->entityManager()->flush();

        $stock->shipPart('2026-0003', Fixtures::BOOK, 2, Fixtures::at(11));
        $stock->ship('2026-0003', Fixtures::at(12));
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        self::assertSame([['', 3]], array_map(static fn (OwnerQuantity $split): array => [$split->owner->key(), $split->quantity], $this->service(ConsignmentStock::class)->split(Fixtures::BOOK)));
        self::assertSame(3, $this->service(StockRepository::class)->level(Fixtures::BOOK, Fixtures::SHELF)?->onHand());
    }

    public function testAConsignorIsPaidAndGetsTheUnsoldCopiesBack(): void
    {
        $author = Owner::consignor(Fixtures::AUTHOR);
        $this->service(ConsignorAgreements::class)->agree($author, Fixtures::utc('2026-11-01'), 7, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Weekly, Fixtures::utc('2026-11-01'));
        $this->service(ConsignmentStock::class)->receive(Fixtures::mixedReceipt(), Fixtures::utc('2026-11-02 10:00:00'));
        $this->service(ConsignmentStock::class)->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::cad(2000), Fixtures::utc('2026-11-03 10:00:00'));
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $statements = $this->service(ConsignorStatements::class);
        $statement = $statements->issue($author, Currency::of('CAD'), $statements->lastPeriod($author, Fixtures::utc('2026-11-10')), Fixtures::utc('2026-11-10'));
        $payouts = $this->service(ConsignorPayouts::class);
        $payouts->record('PAY-1', $author, $statement->share(), Fixtures::utc('2026-11-10'), 'transfer', 'TRX-1', Fixtures::utc('2026-11-10'));
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        self::assertSame('ST-00000001', $statement->number);
        self::assertSame(['2026-11-02', 3000], [$statement->period()->start->format('Y-m-d'), $statement->share()->minorUnits]);
        self::assertStringContainsString('ST-00000001,author-ann,CAD', $this->service(StatementCsv::class)->write($statement));
        self::assertSame(PayoutState::Paid, $payouts->balance($author, Currency::of('CAD'))->state);

        $unsold = $this->service(UnsoldStock::class);
        [$due] = $unsold->due(Fixtures::utc('2026-11-10'));
        self::assertSame([3, '2026-11-09'], [$due->units, $due->dueAt->format('Y-m-d')]);
        $unsold->giveBack('RS-1', $author, $due->location, [$due->sku => $due->units], Fixtures::utc('2026-11-10'));
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        self::assertSame([], $unsold->due(Fixtures::utc('2026-11-10')));
        self::assertSame([['', 5]], array_map(static fn (OwnerQuantity $split): array => [$split->owner->key(), $split->quantity], $this->service(ConsignmentStock::class)->split(Fixtures::BOOK)));
        // The sale was written to the ledger only, as a till does before the order ships: the shelf lost the three copies
        // given back.
        self::assertSame(7, $this->service(StockRepository::class)->level(Fixtures::BOOK, Fixtures::SHELF)?->onHand());
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
