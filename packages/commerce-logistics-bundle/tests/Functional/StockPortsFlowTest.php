<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\Purchasing;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Event\ShippedLine;
use Phil\CommerceLogisticsBundle\Domain\Event\UnitsShipped;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseLine;
use Phil\CommerceLogisticsBundle\Domain\Repository\LocationRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\PurchaseOrderRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle;
use Phil\CommerceLogisticsBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * What a ledger of owners reads from the module through the container: the shipments announced on the application's
 * event dispatcher, customer returns and adjustments in the journal, and the unit cost and owner of purchase order
 * lines, each stored and read back from a fresh unit of work.
 */
#[CoversClass(PhilCommerceLogisticsBundle::class)]
final class StockPortsFlowTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->entityManager();
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $locations = $this->service(LocationRepository::class, LocationRepository::class);
        $locations->addLocation(Location::of('warehouse', 'Warehouse', 10));
        $locations->addLocation(Location::of('returns', 'Returns'));

        $entityManager->flush();
    }

    public function testShippedUnitsReachTheApplicationsListenersAndComeBack(): void
    {
        $events = [];
        $this->service('event_dispatcher', TraceableEventDispatcher::class)->addListener(UnitsShipped::class, static function (UnitsShipped $event) use (&$events): void {
            $events[] = $event;
        });
        $at = new DateTimeImmutable('2026-11-21T10:00:00+00:00');
        $stock = $this->service(Stock::class, Stock::class);
        $stock->receive('DICE', 'warehouse', 5, $at);
        $this->entityManager()->flush();

        $order = Order::open('2026-0001', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'DICE'), 'Dice', $price, 'goods'), 4, $price);
        $order->place($at);

        $stock->reserve($order, $at);
        $this->entityManager()->flush();

        $stock->shipPart('2026-0001', 'DICE', 3, $at);
        $this->entityManager()->flush();
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        self::assertSame([['2026-0001', [['DICE', 'warehouse', 3]]]], array_map(
            static fn (UnitsShipped $event): array => [$event->orderNumber, array_map(static fn (ShippedLine $line): array => [$line->sku, $line->location, $line->units], $event->lines)],
            $events,
        ));

        $stock->returnFromCustomer('2026-0001', 'DICE', 'returns', 2, $at);
        $stock->adjust('DICE', 'warehouse', -1, $at, 'DAMAGED');
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $repository = $this->service(StockRepository::class, StockRepository::class);
        self::assertSame([[3, 2, 1]], array_map(static fn (Reservation $reservation): array => [$reservation->shipped(), $reservation->returned(), $reservation->returnable()], $repository->reservationsOf('2026-0001')));
        self::assertSame([[MovementKind::Returned, 2, '2026-0001']], array_map(static fn (StockMovement $movement): array => [$movement->kind, $movement->onHandChange, $movement->reference], $repository->journal('DICE', 'returns')));
        self::assertSame(
            [[MovementKind::Received, 5, null], [MovementKind::Reserved, 0, '2026-0001'], [MovementKind::Shipped, -3, '2026-0001'], [MovementKind::Adjusted, -1, 'DAMAGED']],
            array_map(static fn (StockMovement $movement): array => [$movement->kind, $movement->onHandChange, $movement->reference], $repository->journal('DICE', 'warehouse')),
        );
        self::assertSame([1, 2], [$repository->level('DICE', 'warehouse')?->onHand(), $repository->level('DICE', 'returns')?->onHand()]);
    }

    public function testThePurchaseOrderLinesKeepTheirCostAndOwner(): void
    {
        $at = new DateTimeImmutable('2026-11-21T10:00:00+00:00');
        $purchasing = $this->service(Purchasing::class, Purchasing::class);
        $purchasing->order('PO-1', 'Books & Co', 'warehouse', ['DICE' => 2, 'BOOK' => PurchaseLine::of(5, Money::of(1_250, Currency::of('CAD')), 'consignor-17')], $at);
        $purchasing->order('PO-2', 'Dice Works', 'warehouse', ['DICE' => 2], $at);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $order = $purchasing->receive('PO-1', ['BOOK' => 5], $at);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $line = $order->line('BOOK');
        self::assertSame([5, 1_250, 'CAD', 'consignor-17'], [$line?->units, $line?->unitCost?->minorUnits, $line?->unitCost?->currency->code, $line?->owner]);
        $plain = $this->service(PurchaseOrderRepository::class, PurchaseOrderRepository::class)->findPurchaseOrder('PO-2')?->line('DICE');
        self::assertSame([2, null, null], [$plain?->units, $plain?->unitCost, $plain?->owner]);
        self::assertSame([null], $this->entityManager()->getConnection()->fetchFirstColumn("SELECT line_terms FROM phil_commerce_logistics_purchase_order WHERE number = 'PO-2'"));
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
