<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Application\DeferredSales;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Deferred\StockCommitment;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\DeferredSalePolicy;
use Phil\CommerceBundle\Domain\ValueObject\Deposit;
use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\ConventionStock;
use Phil\CommerceLogisticsBundle\Application\DeliveryOptions;
use Phil\CommerceLogisticsBundle\Application\Document\PickingSlip;
use Phil\CommerceLogisticsBundle\Application\Purchasing;
use Phil\CommerceLogisticsBundle\Application\SerialNumbers;
use Phil\CommerceLogisticsBundle\Application\Shipments;
use Phil\CommerceLogisticsBundle\Application\ShippingDocuments;
use Phil\CommerceLogisticsBundle\Application\ShippingLabels;
use Phil\CommerceLogisticsBundle\Application\ShippingProcessor;
use Phil\CommerceLogisticsBundle\Application\ShippingWaves;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Delivery\DeliveryKind;
use Phil\CommerceLogisticsBundle\Domain\Model\Convention;
use Phil\CommerceLogisticsBundle\Domain\Model\DeliveryMethod;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\PrintJob;
use Phil\CommerceLogisticsBundle\Domain\Model\PurchaseOrder;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\SerialNumber;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingCost;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingWave;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Model\StockPolicy;
use Phil\CommerceLogisticsBundle\Domain\Repository\ConventionRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\DeliveryMethodRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\LocationRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\PurchaseOrderRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShipmentRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShippingCostRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockPolicyRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\Domain\Stock\ReservationState;
use Phil\CommerceLogisticsBundle\Infrastructure\Doctrine\DoctrineLogisticsRepository;
use Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle;
use Phil\CommerceLogisticsBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Stock received, an order reserved and shipped, and its delivery charged, through the container; every level and
 * reservation read back from a fresh unit of work.
 */
#[CoversClass(PhilCommerceLogisticsBundle::class)]
#[CoversClass(DoctrineLogisticsRepository::class)]
final class LogisticsFlowTest extends KernelTestCase
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
        $locations->addLocation(Location::of('store', 'Store'));
        $this->service(DeliveryMethodRepository::class, DeliveryMethodRepository::class)->addMethod(DeliveryMethod::of('post', 'Postes Canada', DeliveryKind::Shipping, Money::of(1_200, Currency::of('CAD')), zones: ['QC']));
        $entityManager->flush();
    }

    public function testAnOrderIsReservedShippedAndCharged(): void
    {
        $stock = $this->service(Stock::class, Stock::class);
        $at = new DateTimeImmutable('2026-11-03T10:00:00+00:00');
        $stock->receive('DICE', 'warehouse', 2, $at, 'PO-1');
        $stock->receive('DICE', 'store', 5, $at, 'PO-1');
        $this->entityManager()->flush();

        $order = Order::open('2026-0001', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'DICE'), 'Dice', $price, 'goods'), 3, $price);
        $order->changeTaxZone('QC');
        $order->chooseDeliveryMethod('post');
        $this->service(ShippingProcessor::class, ShippingProcessor::class)->process($order);
        $order->place($at);

        self::assertSame(['post'], array_map(static fn (DeliveryMethod $method): string => $method->code, $this->service(DeliveryOptions::class, DeliveryOptions::class)->for($order)));
        self::assertSame([1_200], array_map(static fn (Adjustment $adjustment): int => $adjustment->amount->minorUnits, $order->adjustments(AdjustmentType::Shipping)));

        $stock->reserve($order, $at);
        $this->entityManager()->flush();
        $stock->ship('2026-0001', $at->modify('+1 day'));
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $repository = $this->service(StockRepository::class, StockRepository::class);
        self::assertSame([0, 0], [$repository->level('DICE', 'warehouse')?->onHand(), $repository->level('DICE', 'warehouse')?->reserved()]);
        self::assertSame([4, 0], [$repository->level('DICE', 'store')?->onHand(), $repository->level('DICE', 'store')?->reserved()]);
        self::assertEqualsCanonicalizing(['store', 'warehouse'], array_map(static fn (StockLevel $level): string => $level->location, $repository->levelsOf('DICE')));
        self::assertSame([ReservationState::Shipped, ReservationState::Shipped], array_map(static fn (Reservation $reservation): ReservationState => $reservation->state(), $repository->reservationsOf('2026-0001')));
        self::assertSame([5, 1, -2], array_map(static fn (StockMovement $movement): int => $movement->onHandChange + $movement->reservedChange, $repository->journal('DICE', 'store')));
        self::assertSame(4, $stock->available('DICE'));
        self::assertSame('Store', $this->service(LocationRepository::class, LocationRepository::class)->findLocation('store')?->name);
        self::assertSame('Postes Canada', $this->service(DeliveryMethodRepository::class, DeliveryMethodRepository::class)->findMethod('post')?->label);
    }

    /**
     * Each lookup reads only its own rows — the open locations, the active methods, one SKU, one order — in the order
     * the repository promises.
     */
    public function testEachLookupReadsItsOwnRowsInOrder(): void
    {
        $entityManager = $this->entityManager();
        $locations = $this->service(LocationRepository::class, LocationRepository::class);
        $locations->addLocation(Location::of('annex', 'Annex', 5));
        $locations->addLocation(Location::of('basement', 'Basement', 20));
        $locations->findLocation('basement')?->close();

        $methods = $this->service(DeliveryMethodRepository::class, DeliveryMethodRepository::class);
        $cad = Money::of(500, Currency::of('CAD'));
        $methods->addMethod(DeliveryMethod::of('a-slow', 'Slow', DeliveryKind::Shipping, $cad));
        $methods->addMethod(DeliveryMethod::of('z-fast', 'Fast', DeliveryKind::Shipping, $cad, priority: 5));
        $methods->addMethod(DeliveryMethod::of('m-gone', 'Gone', DeliveryKind::Shipping, $cad, priority: 9));
        $methods->findMethod('m-gone')?->withdraw();

        $stock = $this->service(Stock::class, Stock::class);
        $at = new DateTimeImmutable('2026-11-03T10:00:00+00:00');
        $stock->receive('DICE', 'warehouse', 4, $at);
        $stock->receive('CUP', 'store', 3, $at);
        $stock->receive('MEEPLE', 'warehouse', 1, $at);

        $entityManager->flush();

        $repository = $this->service(StockRepository::class, StockRepository::class);
        $warehouse = $repository->level('DICE', 'warehouse');
        $store = $repository->level('CUP', 'store');
        self::assertInstanceOf(StockLevel::class, $warehouse);
        self::assertInstanceOf(StockLevel::class, $store);

        // Persisted newest first: only the order of their identifiers and dates can put them back.
        $first = Reservation::hold('2026-0002', $warehouse, 1);
        $second = Reservation::hold('2026-0002', $store, 1);
        $repository->addReservation($second);
        $repository->addReservation($first);
        $repository->addReservation(Reservation::hold('2026-0003', $warehouse, 1));
        $repository->record(StockMovement::of($warehouse, MovementKind::Counted, 0, 0, 'late', $at->modify('+2 days')));
        $repository->record(StockMovement::of($warehouse, MovementKind::Counted, 0, 0, 'early', $at->modify('-2 days')));

        $entityManager->flush();
        $entityManager->clear();

        self::assertSame(['warehouse', 'annex', 'store'], array_map(static fn (Location $location): string => $location->code, $locations->openLocations()));
        self::assertSame(['z-fast', 'a-slow', 'post'], array_map(static fn (DeliveryMethod $method): string => $method->code, $methods->activeMethods()));
        self::assertSame([['DICE', 'warehouse']], array_map(static fn (StockLevel $level): array => [$level->sku, $level->location], $repository->levelsOf('DICE')));
        self::assertEqualsCanonicalizing(['CUP@store', 'DICE@warehouse'], array_map(static fn (StockLevel $level): string => $level->sku.'@'.$level->location, $repository->levelsOfSkus(['DICE', 'CUP'])));
        self::assertSame([$first->id, $second->id], array_map(static fn (Reservation $reservation): string => $reservation->id, $repository->reservationsOf('2026-0002')));
        self::assertSame(['early', null, 'late'], array_map(static fn (StockMovement $movement): ?string => $movement->reference, $repository->journal('DICE', 'warehouse')));
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
    }

    /**
     * The cost of each parcel stored beside the order, and read back against the delivery the buyer paid.
     */
    public function testTheCostOfEachShipmentIsStored(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $cad = Currency::of('CAD');
        $at = new DateTimeImmutable('2026-11-11 12:00');
        $first = Shipment::of('ORDER-9', ['product:A' => 1], $at, 'canada_post', '1234567890123456');
        $second = Shipment::of('ORDER-9', ['product:B' => 1], $at->modify('+1 hour'));
        $shipments = $this->service(ShipmentRepository::class, ShipmentRepository::class);
        $shipments->addShipment($first);
        $shipments->addShipment($second);

        $labels = $this->service(ShippingLabels::class, ShippingLabels::class);
        $labels->record($second, 'Post office', 'counter', Money::of(900, $cad), $at->modify('+1 hour'));
        $labels->record($first, 'canada_post', 'DOM.RP', Money::of(1_266, $cad), $at, '1234567890123456');

        $entityManager->flush();
        $entityManager->clear();

        $costs = $this->service(ShippingCostRepository::class, ShippingCostRepository::class);
        self::assertSame([[$first->id, 1_266, '1234567890123456'], [$second->id, 900, null]], array_map(static fn (ShippingCost $cost): array => [$cost->shipment, $cost->cost->minorUnits, $cost->tracking], $costs->costsOf('ORDER-9')));
        self::assertSame('DOM.RP', $costs->costOf($first->id)?->service);
        self::assertNull($costs->costOf('unknown'));
        self::assertSame([], $costs->costsOf('ORDER-10'));

        $order = Order::open('ORDER-9', $cad);
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Regular', Money::of(1_500, $cad))]);
        self::assertSame(-666, $labels->margin($order)->margin()->minorUnits);
        self::assertInstanceOf(Shipments::class, $this->service(Shipments::class, Shipments::class));
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

    /**
     * Backorders, conventions, purchase orders, serial numbers, documents and waves stored and read back from a fresh
     * unit of work, each lookup through its own query.
     */
    public function testTheStockRoutesDocumentsAndWavesAreStored(): void
    {
        $at = new DateTimeImmutable('2026-12-01T10:00:00+00:00');
        $stock = $this->service(Stock::class, Stock::class);
        $conventions = $this->service(ConventionStock::class, ConventionStock::class);
        $purchasing = $this->service(Purchasing::class, Purchasing::class);
        $purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => 4, 'CONSOLE' => 1], $at, $at->modify('+3 days'));
        $purchasing->receive('PO-1', ['DICE' => 3, 'CONSOLE' => 1], $at);

        $stock->allowBackorders('DICE');
        $conventions->schedule('fall-con', 'Fall Con', new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-21'));
        $conventions->schedule('spring-con', 'Spring Con', new DateTimeImmutable('2027-04-20'), new DateTimeImmutable('2027-04-21'));
        $conventions->enter('fall-con', 'SHIRT-M', 20);
        $this->entityManager()->flush();
        $this->service(SerialNumbers::class, SerialNumbers::class)->register('CONSOLE', 'warehouse', ['SN-1'], $at);
        $waves = $this->service(ShippingWaves::class, ShippingWaves::class);
        $waves->plan('december', 'December run', new DateTimeImmutable('2026-12-10'));
        $waves->plan('released', 'Already gone', new DateTimeImmutable('2026-11-10'));
        $waves->release('released', $at);
        $waves->assign('2026-0001', 'december');
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $order = Order::open('2026-0001', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'DICE'), 'Dice', $price, 'goods'), 5, $price);
        $order->add(Sellable::of(SellableReference::of('product', 'SHIRT-M'), 'Shirt', $price, 'goods'), 1, $price);
        $order->offerAsGift(GiftOptions::of(wrapped: true, message: 'Merci'));
        $order->place($at);

        $stock->reserve($order, $at);
        $documents = $this->service(ShippingDocuments::class, ShippingDocuments::class);
        $slip = $this->entityManager()->wrapInTransaction(static fn (): PickingSlip => $documents->pickingSlip($order, $at));
        $this->entityManager()->clear();

        self::assertSame([5, 2], [$this->service(StockRepository::class, StockRepository::class)->level('DICE', 'warehouse')?->reserved(), $stock->backordered('DICE')]);
        self::assertTrue($this->service(StockPolicyRepository::class, StockPolicyRepository::class)->policy('DICE')?->allowsBackorders());
        self::assertSame(['DICE'], array_map(static fn (StockPolicy $policy): string => $policy->sku, $this->service(StockPolicyRepository::class, StockPolicyRepository::class)->policiesOf(['DICE', 'MAP'])));
        $conventionRepository = $this->service(ConventionRepository::class, ConventionRepository::class);
        self::assertSame(['fall-con'], array_map(static fn (Convention $convention): string => $convention->code, $conventionRepository->conventionsMeeting($at, $at->modify('+30 days'))));
        self::assertSame([], $conventionRepository->conventionsMeeting(new DateTimeImmutable('2026-12-22'), new DateTimeImmutable('2027-04-19')));
        self::assertSame(['SHIRT-M'], $conventionRepository->conventionSkus(['DICE', 'SHIRT-M']));
        self::assertSame([], $conventionRepository->conventionSkus([]));
        self::assertSame([['SHIRT-M', 1]], array_map(static fn (PrintJob $job): array => [$job->sku, $job->quantity], $conventionRepository->printJobsOf('2026-0001')));
        self::assertSame(['SHIRT-M' => 20], $conventions->planned('fall-con'));
        self::assertSame([1, 0], [$purchasing->onOrder('DICE'), $purchasing->onOrder('CONSOLE')]);
        self::assertSame(['PO-1'], array_map(static fn (PurchaseOrder $purchase): string => $purchase->number, $this->service(PurchaseOrderRepository::class, PurchaseOrderRepository::class)->openPurchaseOrders()));
        self::assertSame('PS-00000001', $slip->number);
        self::assertSame('PS-00000001', $this->entityManager()->wrapInTransaction(static fn (): PickingSlip => $documents->pickingSlip($order, $at))->number);
        self::assertSame(['december'], array_map(static fn (ShippingWave $wave): string => $wave->code, $waves->planned()));
        self::assertSame('2026-12-10', $waves->estimatedOn('2026-0001')?->format('Y-m-d'));

        $serials = $this->service(SerialNumbers::class, SerialNumbers::class);
        $serials->ship('2026-0001', 'CONSOLE', ['SN-1'], $at);

        $waves->withdraw('2026-0001');
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        self::assertSame(['SN-1'], array_map(static fn (SerialNumber $serial): string => $serial->serial, $serials->ofOrder('2026-0001')));
        self::assertSame([], $waves->ordersOf('december'));
        self::assertNull($waves->waveOf('2026-0001'));
    }

    /**
     * The core's deferred sales hold and free the stock of their orders through the module.
     */
    public function testTheStockOfADeferredSaleIsCommittedThroughTheCore(): void
    {
        $at = new DateTimeImmutable('2026-12-01T10:00:00+00:00');
        $stock = $this->service(Stock::class, Stock::class);
        $stock->receive('DICE', 'warehouse', 4, $at);

        $order = Order::open('2026-0002', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'DICE'), 'Dice', $price, 'goods'), 3, $price);
        $order->place($at);
        $this->service(OrderRepository::class, OrderRepository::class)->add($order);
        $this->entityManager()->flush();

        $sales = $this->service(DeferredSales::class, DeferredSales::class);
        $sales->open($order, DeferredSalePolicy::preorder(Deposit::share(2_000), commitment: StockCommitment::OnOrder), $at);
        self::assertSame(1, $stock->available('DICE'));
    }
}
