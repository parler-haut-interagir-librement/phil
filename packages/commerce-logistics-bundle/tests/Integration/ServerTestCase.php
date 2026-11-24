<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\Costing;
use Phil\CommerceLogisticsBundle\Application\InventoryValue;
use Phil\CommerceLogisticsBundle\Application\Purchasing;
use Phil\CommerceLogisticsBundle\Application\SerialNumbers;
use Phil\CommerceLogisticsBundle\Application\ShippingWaves;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostEntryKind;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostingMethod;
use Phil\CommerceLogisticsBundle\Domain\Cost\VarianceReason;
use Phil\CommerceLogisticsBundle\Domain\Delivery\DeliveryKind;
use Phil\CommerceLogisticsBundle\Domain\Document\ShippingDocumentKind;
use Phil\CommerceLogisticsBundle\Domain\Model\Convention;
use Phil\CommerceLogisticsBundle\Domain\Model\ConventionQuantity;
use Phil\CommerceLogisticsBundle\Domain\Model\CostEntry;
use Phil\CommerceLogisticsBundle\Domain\Model\CostLayer;
use Phil\CommerceLogisticsBundle\Domain\Model\DeliveryMethod;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\PrintJob;
use Phil\CommerceLogisticsBundle\Domain\Model\PurchaseOrder;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\SerialNumber;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingCost;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingDocument;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingWave;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Model\StockPolicy;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseLine;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\Domain\ValueObject\SaleCost;
use Phil\CommerceLogisticsBundle\Domain\ValueObject\ValuedStock;
use Phil\CommerceLogisticsBundle\Infrastructure\Doctrine\DoctrineLogisticsRepository;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * The module's mapping and queries on a real database: the tables created from the mapping, dates and JSON read back
 * as they were given, each query's filters and order, two adjustments of one level at once, and a database of the
 * first version brought to the current mapping by adding columns only.
 */
abstract class ServerTestCase extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineLogisticsRepository $repository;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    /**
     * How to reach the database: the server named by the variable, or null to skip.
     *
     * @return array<string, mixed>|null
     */
    protected function parameters(): ?array
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            return null;
        }

        return new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url);
    }

    #[Override]
    protected function setUp(): void
    {
        if (null === $this->parameters()) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $this->entityManager = $this->newEntityManager();
        $tool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $this->repository = new DoctrineLogisticsRepository($this->entityManager);
    }

    /**
     * A unit of work of its own, on a connection of its own: another request.
     */
    private function newEntityManager(): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        /** @phpstan-ignore argument.type (the parameters come from DBAL's own parser, or name a SQLite file) */
        return new EntityManager(DriverManager::getConnection($this->parameters() ?? []), $configuration);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->getConnection()->close();
        }
    }

    public function testStockPurchasesAndSerialsAreReadBack(): void
    {
        $at = new DateTimeImmutable('2026-11-12 10:00:00');
        $this->repository->addLocation(Location::of('warehouse', 'Entrepôt', 10));
        $this->entityManager->flush();
        $stock = new Stock($this->repository, $this->repository, $this->repository);
        $purchasing = new Purchasing($this->repository, $this->repository, $stock);
        $purchasing->order('PO-1', 'Dice Works', 'warehouse', ['1234' => 4, 'DICE' => 2], $at, new DateTimeImmutable('2026-11-20'));

        $stock->allowBackorders('DICE');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $purchasing->receive('PO-1', ['1234' => 2], $at);
        new SerialNumbers($this->repository, $this->repository)->register('1234', 'warehouse', ['SN-1', 'SN-2'], $at);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame([2, 2], [$purchasing->onOrder('1234'), $purchasing->onOrder('DICE')]);
        self::assertSame('2026-11-20', $this->repository->findPurchaseOrder('PO-1')?->expectedOn()?->format('Y-m-d'));
        self::assertTrue($stock->allowsBackorders('DICE'));
        self::assertSame(2, $this->repository->serialsInStock('1234', 'warehouse'));
        self::assertSame(0, $this->repository->serialsInStock('1234', 'store'));
    }

    public function testConventionsDocumentsAndWavesAreFoundByTheirQueries(): void
    {
        $fall = Convention::of('fall-con', 'Fall Con', new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-21'));
        $this->repository->addConvention($fall);
        $this->repository->addConvention(Convention::of('spring-con', 'Spring Con', new DateTimeImmutable('2027-04-20'), new DateTimeImmutable('2027-04-20')));
        $this->repository->addQuantity(ConventionQuantity::of($fall, 'SHIRT-M', 20));
        $this->repository->addQuantity(ConventionQuantity::of($fall, 'SHIRT-L', 0));

        $shipment = Shipment::of('2026-0001', ['product:A' => 1], new DateTimeImmutable());
        $this->repository->addShipment($shipment);
        $this->repository->addDocument(ShippingDocument::pickingSlip('PS-1', '2026-0001', new DateTimeImmutable()));
        $this->repository->addDocument(ShippingDocument::deliveryNote('DN-1', $shipment, new DateTimeImmutable()));

        $waves = new ShippingWaves($this->repository);
        $waves->plan('later', 'Later', new DateTimeImmutable('2027-02-01'));
        $waves->plan('sooner', 'Sooner', new DateTimeImmutable('2027-01-01'));
        $waves->plan('gone', 'Gone', new DateTimeImmutable('2026-10-01'));
        $waves->release('gone', new DateTimeImmutable());

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['fall-con'], array_map(static fn (Convention $convention): string => $convention->code, $this->repository->conventionsMeeting(new DateTimeImmutable('2026-12-21 23:00'), new DateTimeImmutable('2027-04-19'))));
        self::assertSame(['fall-con', 'spring-con'], array_map(static fn (Convention $convention): string => $convention->code, $this->repository->conventionsMeeting(new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2027-04-20'))));
        self::assertSame(['SHIRT-L', 'SHIRT-M'], $this->repository->conventionSkus(['SHIRT-M', 'SHIRT-L', 'DICE']));
        self::assertSame(['PS-1', null], [$this->repository->findDocument(ShippingDocumentKind::PickingSlip, '2026-0001', null)?->number, $this->repository->findDocument(ShippingDocumentKind::PickingSlip, '2026-0001', null)?->shipment()]);
        self::assertSame(['DN-1', $shipment->id], [$this->repository->findDocument(ShippingDocumentKind::DeliveryNote, '2026-0001', $shipment->id)?->number, $this->repository->findDocument(ShippingDocumentKind::DeliveryNote, '2026-0001', $shipment->id)?->shipment()]);
        self::assertNull($this->repository->findDocument(ShippingDocumentKind::DeliveryNote, '2026-0001', null));
        self::assertNull($this->repository->findDocument(ShippingDocumentKind::PickingSlip, '2026-0001', $shipment->id));
        self::assertSame([['product:A' => 1]], array_map(static fn (Shipment $shipped): array => $shipped->lines, $this->repository->shipmentsOf('2026-0001')));
        self::assertSame(['sooner', 'later'], array_map(static fn (ShippingWave $wave): string => $wave->code, $waves->planned()));
    }

    public function testTwoAdjustmentsOfOneLevelAtOnceBothCount(): void
    {
        $at = new DateTimeImmutable('2026-11-21 10:00:00');
        $this->repository->addLocation(Location::of('warehouse', 'Entrepôt', 10));
        new Stock($this->repository, $this->repository)->receive('DICE', 'warehouse', 10, $at);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $other = $this->newEntityManager();
        $otherRepository = new DoctrineLogisticsRepository($other);
        new Stock($this->repository, $this->repository)->adjust('DICE', 'warehouse', -3, $at, 'COUNT-1');
        new Stock($otherRepository, $otherRepository)->adjust('DICE', 'warehouse', 5, $at, 'COUNT-2');
        $this->entityManager->flush();

        try {
            $other->flush();
            self::fail('Two adjustments made over the same copy of a level were both written.');
        } catch (OptimisticLockException) {
            $other->getConnection()->close();
        }

        $retry = $this->newEntityManager();
        $retryRepository = new DoctrineLogisticsRepository($retry);
        new Stock($retryRepository, $retryRepository)->adjust('DICE', 'warehouse', 5, $at, 'COUNT-2');
        $retry->flush();
        $retry->getConnection()->close();
        $this->entityManager->clear();

        self::assertSame([12, 3], [$this->repository->level('DICE', 'warehouse')?->onHand(), $this->repository->level('DICE', 'warehouse')?->version()]);
        self::assertSame(
            [[MovementKind::Received, 10, null], [MovementKind::Adjusted, -3, 'COUNT-1'], [MovementKind::Adjusted, 5, 'COUNT-2']],
            array_map(static fn (StockMovement $movement): array => [$movement->kind, $movement->onHandChange, $movement->reference], $this->repository->journal('DICE', 'warehouse')),
        );
    }

    public function testReturnsAndTheTermsOfPurchaseLinesAreReadBack(): void
    {
        $at = new DateTimeImmutable('2026-11-21 10:00:00');
        $this->repository->addLocation(Location::of('warehouse', 'Entrepôt', 10));
        $this->entityManager->flush();
        $stock = new Stock($this->repository, $this->repository);
        $purchasing = new Purchasing($this->repository, $this->repository, $stock);
        $purchasing->order('PO-1', 'Books & Co', 'warehouse', ['1234' => PurchaseLine::of(3, Money::of(1_250, Currency::of('CAD')), 'Zoë Café'), 'DICE' => 2], $at);
        $purchasing->order('PO-2', 'Dice Works', 'warehouse', ['DICE' => 2], $at);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $line = $this->repository->findPurchaseOrder('PO-1')?->line('1234');
        self::assertSame([3, 1_250, 'CAD', 'Zoë Café'], [$line?->units, $line?->unitCost?->minorUnits, $line?->unitCost?->currency->code, $line?->owner]);
        self::assertSame([2, null, null], [$this->repository->findPurchaseOrder('PO-1')?->line('DICE')?->units, $this->repository->findPurchaseOrder('PO-1')?->line('DICE')?->unitCost, $this->repository->findPurchaseOrder('PO-1')?->line('DICE')?->owner]);
        self::assertSame([null], $this->entityManager->getConnection()->fetchFirstColumn("SELECT line_terms FROM phil_commerce_logistics_purchase_order WHERE number = 'PO-2'"));
    }

    /**
     * Every query of the adapter keeps its filters and its order: each row below is written in an order its sort undoes,
     * and ties on the first key are broken by the next.
     */
    public function testEachQueryFiltersAndSortsAsItSays(): void
    {
        $cad = Currency::of('CAD');
        $at = new DateTimeImmutable('2026-11-21 10:00:00');
        $closed = Location::of('closed', 'Closed', 9);
        $closed->close();

        foreach ([Location::of('c-store', 'C', 5), Location::of('a-store', 'A', 1), $closed, Location::of('b-store', 'B', 5)] as $location) {
            $this->repository->addLocation($location);
        }

        $withdrawn = DeliveryMethod::of('withdrawn', 'Withdrawn', DeliveryKind::Shipping, Money::of(100, $cad), priority: 9);
        $withdrawn->withdraw();

        foreach ([DeliveryMethod::of('c-ship', 'C', DeliveryKind::Shipping, Money::of(100, $cad), priority: 5), DeliveryMethod::of('a-ship', 'A', DeliveryKind::Shipping, Money::of(100, $cad), priority: 1), $withdrawn, DeliveryMethod::of('b-ship', 'B', DeliveryKind::Shipping, Money::of(100, $cad), priority: 5)] as $method) {
            $this->repository->addMethod($method);
        }

        $this->entityManager->flush();
        $warehouse = $this->repository->findLocation('a-store') ?? self::fail('No location.');
        $levelOfDice = StockLevel::of('DICE', $warehouse);
        $levelOfDice->receive(5);

        $this->repository->addLevel($levelOfDice);
        $levelOfBook = StockLevel::of('BOOK', $warehouse);
        $this->repository->addLevel($levelOfBook);
        $this->repository->addLevel(StockLevel::of('CARD', $warehouse));
        $this->repository->addLevel(StockLevel::of('MAP', $warehouse));
        $this->repository->record(StockMovement::of($levelOfBook, MovementKind::Received, 1, 0, 'book', $at));

        // The journal: the later moment written first, then two of one moment.
        $this->repository->record(StockMovement::of($levelOfDice, MovementKind::Received, 2, 0, 'late', $at->modify('+1 hour')));
        $this->repository->record(StockMovement::of($levelOfDice, MovementKind::Received, 1, 0, 'first', $at));
        $this->repository->record(StockMovement::of($levelOfDice, MovementKind::Received, 2, 0, 'second', $at));

        $first = Reservation::hold('2026-0001', $levelOfDice, 1);
        $second = Reservation::hold('2026-0001', $levelOfDice, 1);
        $this->repository->addReservation($second);
        $this->repository->addReservation($first);
        $this->repository->addReservation(Reservation::hold('2026-0002', $levelOfDice, 1));

        // The latest moment is the first written, and so has the lowest key: the order of the keys is not the order asked.
        $late = Shipment::of('2026-0001', ['product:DICE' => 1], $at->modify('+1 day'));
        $early = Shipment::of('2026-0001', ['product:DICE' => 1], $at);
        $tied = Shipment::of('2026-0001', ['product:DICE' => 1], $at);
        $foreign = Shipment::of('2026-0009', ['product:DICE' => 1], $at);

        foreach ([$late, $tied, $early, $foreign] as $shipment) {
            $this->repository->addShipment($shipment);
        }

        $this->repository->addCost(ShippingCost::of($late, 'post', 'regular', Money::of(900, $cad), $at->modify('+1 day')));
        $this->repository->addCost(ShippingCost::of($tied, 'post', 'regular', Money::of(700, $cad), $at));
        $this->repository->addCost(ShippingCost::of($early, 'post', 'regular', Money::of(800, $cad), $at));
        $this->repository->addCost(ShippingCost::of($foreign, 'post', 'regular', Money::of(600, $cad), $at));
        $this->repository->addPolicy(StockPolicy::of('DICE'));
        $this->repository->addPolicy(StockPolicy::of('BOOK'));
        $this->repository->addPolicy(StockPolicy::of('ZINE'));

        $con = Convention::of('con', 'Con', new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-21'));
        $other = Convention::of('other-con', 'Other', new DateTimeImmutable('2026-12-19'), new DateTimeImmutable('2026-12-20'));
        $this->repository->addConvention($other);
        $this->repository->addConvention($con);
        $this->repository->addConvention(Convention::of('b-con', 'B', new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-20')));
        $this->repository->addQuantity(ConventionQuantity::of($con, 'SHIRT-M', 5));
        $this->repository->addQuantity(ConventionQuantity::of($con, 'SHIRT-L', 5));
        $this->repository->addQuantity(ConventionQuantity::of($other, 'SHIRT-M', 5));

        $firstJob = PrintJob::of('2026-0001', 'SHIRT-M', 1, $at);
        $secondJob = PrintJob::of('2026-0001', 'SHIRT-L', 1, $at);
        $this->repository->addPrintJob($secondJob);
        $this->repository->addPrintJob($firstJob);
        $this->repository->addPrintJob(PrintJob::of('2026-0002', 'SHIRT-L', 1, $at));

        $closedOrder = PurchaseOrder::of('PO-0', 'Dice Works', $warehouse, ['DICE' => 1], $at->modify('-1 day'));
        $closedOrder->close();

        // Ordered last but numbered first, and two of one moment: by moment, then by number.
        foreach ([PurchaseOrder::of('PO-3', 'Dice Works', $warehouse, ['DICE' => 1], $at->modify('+1 day')), PurchaseOrder::of('PO-4', 'Dice Works', $warehouse, ['DICE' => 1], $at), PurchaseOrder::of('PO-2', 'Dice Works', $warehouse, ['DICE' => 1], $at), $closedOrder, PurchaseOrder::of('PO-1', 'Dice Works', $warehouse, ['DICE' => 1], $at->modify('+2 days'))] as $order) {
            $this->repository->addPurchaseOrder($order);
        }

        $shipped = SerialNumber::of($levelOfDice, 'SN-3', $at);
        $shipped->ship('2026-0001', $at);

        foreach ([$shipped, SerialNumber::of($levelOfDice, 'SN-1', $at), SerialNumber::of(StockLevel::of('BOOK', $warehouse), 'SN-2', $at)] as $serial) {
            $this->repository->addSerial($serial);
        }

        $bookSerial = SerialNumber::of(StockLevel::of('BOOK', $warehouse), 'SN-9', $at);
        $bookSerial->ship('2026-0001', $at);

        $this->repository->addSerial($bookSerial);
        $diceSerial = SerialNumber::of($levelOfDice, 'SN-2', $at);
        $diceSerial->ship('2026-0001', $at);

        $this->repository->addSerial($diceSerial);

        $waves = new ShippingWaves($this->repository);
        $waves->plan('b-wave', 'B', new DateTimeImmutable('2027-01-01'));
        $waves->plan('a-wave', 'A', new DateTimeImmutable('2027-01-01'));
        $waves->plan('early', 'Early', new DateTimeImmutable('2026-12-01'));
        $waves->assign('2026-0003', 'a-wave');
        $waves->assign('2026-0001', 'a-wave');
        $waves->assign('2026-0002', 'b-wave');

        $this->entityManager->flush();
        $waves->withdraw('2026-0002');
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['b-store', 'c-store', 'a-store'], array_map(static fn (Location $location): string => $location->code, $this->repository->openLocations()));
        self::assertSame(['b-ship', 'c-ship', 'a-ship'], array_map(static fn (DeliveryMethod $method): string => $method->code, $this->repository->activeMethods()));
        self::assertSame(['first', 'second', 'late'], array_map(static fn (StockMovement $movement): ?string => $movement->reference, $this->repository->journal('DICE', 'a-store')));
        self::assertSame([], $this->repository->journal('DICE', 'b-store'));
        self::assertSame([$first->id, $second->id], array_map(static fn (Reservation $reservation): string => $reservation->id, $this->repository->reservationsOf('2026-0001')));
        self::assertSame([$early->id, $tied->id, $late->id], array_map(static fn (Shipment $shipment): string => $shipment->id, $this->repository->shipmentsOf('2026-0001')));
        self::assertSame([800, 700, 900], array_map(static fn (ShippingCost $cost): int => $cost->cost->minorUnits, $this->repository->costsOf('2026-0001')));
        self::assertSame(700, $this->repository->costOf($tied->id)?->cost->minorUnits);
        self::assertSame([['BOOK', 'DICE'], [], ['BOOK', 'CARD', 'DICE'], []], [
            $this->sorted(array_map(static fn (StockPolicy $policy): string => $policy->sku, $this->repository->policiesOf(['DICE', 'BOOK', 'CARD']))),
            $this->repository->policiesOf([]),
            $this->sorted(array_map(static fn (StockLevel $level): string => $level->sku, $this->repository->levelsOfSkus(['DICE', 'BOOK', 'CARD', 'NONE']))),
            $this->repository->levelsOfSkus([]),
        ]);
        self::assertSame(['other-con', 'b-con', 'con'], array_map(static fn (Convention $convention): string => $convention->code, $this->repository->conventionsMeeting(new DateTimeImmutable('2026-12-20 23:00'), new DateTimeImmutable('2026-12-20 01:00'))));
        self::assertSame(['con'], array_map(static fn (Convention $convention): string => $convention->code, $this->repository->conventionsMeeting(new DateTimeImmutable('2026-12-21 23:59'), new DateTimeImmutable('2026-12-21 00:00'))));
        self::assertSame(['SHIRT-L', 'SHIRT-M'], array_map(static fn (ConventionQuantity $quantity): string => $quantity->sku, $this->repository->quantitiesOf('con')));
        self::assertSame(['SHIRT-L', 'SHIRT-M'], $this->repository->conventionSkus(['SHIRT-M', 'SHIRT-L', 'NONE']));
        self::assertSame([], $this->repository->conventionSkus([]));
        self::assertSame(5, $this->repository->quantity('con', 'SHIRT-M')?->quantity());
        self::assertSame([$firstJob->id, $secondJob->id], array_map(static fn (PrintJob $job): string => $job->id, $this->repository->printJobsOf('2026-0001')));
        self::assertSame(['PO-2', 'PO-4', 'PO-3', 'PO-1'], array_map(static fn (PurchaseOrder $order): string => $order->number, $this->repository->openPurchaseOrders()));
        self::assertSame([1, 1], [$this->repository->serialsInStock('DICE', 'a-store'), $this->repository->serialsInStock('BOOK', 'a-store')]);
        self::assertSame([['BOOK', 'SN-9'], ['DICE', 'SN-2'], ['DICE', 'SN-3']], array_map(static fn (SerialNumber $serial): array => [$serial->sku, $serial->serial], $this->repository->serialsOfOrder('2026-0001')));
        self::assertSame(['early', 'a-wave', 'b-wave'], array_map(static fn (ShippingWave $wave): string => $wave->code, $this->repository->plannedWaves()));
        self::assertSame(['2026-0001', '2026-0003'], $this->repository->ordersOfWave('a-wave'));
        self::assertSame([], $this->repository->ordersOfWave('b-wave'));
        self::assertNull($this->repository->assignmentOf('2026-0002'));
        self::assertSame('a-wave', $this->repository->assignmentOf('2026-0001')?->wave());
    }

    /**
     * What a query finds and what the unit of work holds are read together: layers and entries written and not flushed
     * yet join the ones stored, the ones removed leave, and those of another SKU, place, order, owner or kind are no
     * part of an answer.
     */
    public function testTheCostLayersAreReadWithWhatIsNotFlushedYet(): void
    {
        $cad = Currency::of('CAD');
        $at = new DateTimeImmutable('2026-11-21 10:00:00');
        $shop = static fn (string $sku, string $location, string $reference, string $moment): CostLayer => CostLayer::shop($sku, $location, 1, Money::of(100, $cad), $at->modify($moment), $reference);

        $removed = $shop('DICE', 'warehouse', 'L3', '+3 hours');
        $stored = [
            $removed,
            $shop('DICE', 'warehouse', 'L1', '+1 hour'),
            $shop('DICE', 'warehouse', 'L2a', '+2 hours'),
            $shop('DICE', 'warehouse', 'L2b', '+2 hours'),
            $shop('BOOK', 'warehouse', 'B1', '+1 hour'),
            $shop('DICE', 'store', 'S1', '+1 hour'),
            CostLayer::owned('DICE', 'warehouse', '17', 1, $cad, $at->modify('+5 hours'), 'O1'),
        ];

        foreach ($stored as $layer) {
            $this->repository->addLayer($layer);
        }

        $this->entityManager->flush();

        foreach ([$shop('BOOK', 'warehouse', 'B2', '+1 hour'), $shop('DICE', 'store', 'S2', '+1 hour'), CostLayer::owned('DICE', 'warehouse', '17', 1, $cad, $at->modify('+6 hours'), 'O2'), $shop('DICE', 'warehouse', 'L4', '+4 hours')] as $layer) {
            $this->repository->addLayer($layer);
        }

        $this->repository->removeLayer($removed);

        $reference = static fn (CostLayer $layer): ?string => $layer->reference;
        // By the moment received, the layers of one moment by their key; the owner's are in the level too.
        self::assertSame(['L1', 'L2a', 'L2b', 'L4', 'O1', 'O2'], array_map($reference, $this->repository->layersOf('DICE', 'warehouse')));
        // The shop's, of every place, in no order a server owes; the first one stored is gone, and no gap is left.
        $shops = array_map($reference, $this->repository->shopLayersOf('DICE'));
        self::assertSame([0, 1, 2, 3, 4, 5], array_keys($shops));
        self::assertSame(['L1', 'L2a', 'L2b', 'L4', 'S1', 'S2'], $this->sorted($shops));
    }

    public function testTheCostJournalIsReadWithWhatIsNotFlushedYet(): void
    {
        $cad = Currency::of('CAD');
        $at = new DateTimeImmutable('2026-11-21 10:00:00');
        $shop = static fn (string $sku, CostEntryKind $kind, int $units, int $uncosted, string $reference): CostEntry => CostEntry::shop($sku, 'warehouse', $kind, $units, $uncosted, Money::of(100 * $units, $cad), $reference, $at);

        // Sales of an order, the older key written after the newer one.
        $returned = $shop('DICE', CostEntryKind::Returned, 1, 0, 'O-1');
        $issued = $shop('DICE', CostEntryKind::Issued, -2, 0, 'O-1');
        $noise = static fn (): array => [
            $shop('DICE', CostEntryKind::Issued, -1, 0, 'O-2'),
            $shop('BOOK', CostEntryKind::Issued, -1, 0, 'O-1'),
            $shop('DICE', CostEntryKind::Received, 1, 0, 'O-1'),
        ];

        foreach ([$issued, ...$noise()] as $entry) {
            $this->repository->addEntry($entry);
        }

        $this->entityManager->flush();

        foreach ([$returned, ...$noise()] as $entry) {
            $this->repository->addEntry($entry);
        }

        self::assertSame([$returned->id, $issued->id], array_map(static fn (CostEntry $entry): string => $entry->id, $this->repository->salesEntriesOf('O-1', 'DICE')));

        // The latest receipt with a cost, the shop's: written last among the right ones, and the wrong ones after it.
        $stored = $shop('DICE', CostEntryKind::Received, 2, 0, 'R-1');
        $this->repository->addEntry($stored);
        $this->entityManager->flush();
        $latest = $shop('DICE', CostEntryKind::Received, 2, 1, 'R-2');
        $this->repository->addEntry($latest);

        foreach ([
            $shop('BOOK', CostEntryKind::Received, 1, 0, 'R-3'),
            $shop('DICE', CostEntryKind::Issued, -1, 0, 'R-4'),
            CostEntry::owned('DICE', 'warehouse', CostEntryKind::Received, '17', 1, $cad, 'R-5', $at),
            $shop('DICE', CostEntryKind::Received, 1, 1, 'R-6'),
        ] as $entry) {
            $this->repository->addEntry($entry);
        }

        self::assertSame('R-2', $this->repository->latestCostedReceipt('DICE')?->reference);
        self::assertNull($this->repository->latestCostedReceipt('MAP'));
    }

    public function testTheStockIsValuedWhereItHoldsUnitsOrValueAndNowhereElse(): void
    {
        $cad = Currency::of('CAD');
        $at = new DateTimeImmutable('2026-11-21 10:00:00');
        $entries = [
            CostEntry::shop('ZINE', 'warehouse', CostEntryKind::Received, 3, 0, Money::zero($cad), 'R-1', $at),
            CostEntry::shop('MAP', 'warehouse', CostEntryKind::Received, 1, 0, Money::of(100, $cad), 'R-2', $at),
            CostEntry::shop('MAP', 'warehouse', CostEntryKind::Issued, -1, 0, Money::of(-100, $cad), '2026-0001', $at),
            CostEntry::shop('DICE', 'warehouse', CostEntryKind::Received, 2, 0, Money::of(200, $cad), 'R-3', $at),
            CostEntry::shop('BOOK', 'warehouse', CostEntryKind::Landed, 0, 0, Money::of(86, $cad), 'R-4', $at),
        ];

        foreach ($entries as $entry) {
            $this->repository->addEntry($entry);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(
            [
                ['sku' => 'BOOK', 'location' => 'warehouse', 'currency' => 'CAD', 'units' => 0, 'value' => 86],
                ['sku' => 'DICE', 'location' => 'warehouse', 'currency' => 'CAD', 'units' => 2, 'value' => 200],
                ['sku' => 'ZINE', 'location' => 'warehouse', 'currency' => 'CAD', 'units' => 3, 'value' => 0],
            ],
            $this->repository->valuation($at),
        );
    }

    public function testTheCostOfTheStockIsKeptAcrossUnitsOfWork(): void
    {
        $at = new DateTimeImmutable('2026-11-21 10:00:00', new DateTimeZone('America/Toronto'));
        $this->repository->addLocation(Location::of('warehouse', 'Entrepôt', 10));
        $this->repository->addLocation(Location::of('store', 'Magasin', 0));

        $this->entityManager->flush();
        [$stock, $purchasing] = $this->costed(CostingMethod::WeightedAverage);
        $purchasing->order('PO-1', 'Dice Works', 'warehouse', ['DICE' => PurchaseLine::of(4, $this->cad(1_000)), 'LENT' => PurchaseLine::of(2, $this->cad(500), '17')], $at);

        // Two receipts in one unit of work join one pool: the second finds the first before it is flushed.
        $purchasing->receive('PO-1', ['DICE' => 2, 'LENT' => 2], $at, $this->cad(101));

        $stock->receive('DICE', 'warehouse', 2, $at->modify('+1 hour'), 'PO-2', $this->cad(1_200));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame([[null, 4, 4_501], ['17', 2, 0]], $this->layersOf('DICE', 'warehouse', 'LENT'));
        $value = new InventoryValue($this->repository);
        self::assertSame(1_125, $value->unitCost('DICE')?->minorUnits);

        [$stock] = $this->costed(CostingMethod::WeightedAverage);
        $stock->reserve($this->order('2026-0001', ['DICE' => 3, 'LENT' => 1]), $at);
        $this->entityManager->flush();
        $stock->ship('2026-0001', $at->modify('+1 day'));
        $stock->adjustFor('DICE', 'warehouse', -1, VarianceReason::Theft, $at->modify('+2 days'), 'COUNT-1');

        $this->entityManager->flush();
        $this->entityManager->clear();

        // The pool emptied is gone; the journal keeps it.
        self::assertSame([['17', 1, 0]], $this->layersOf('DICE', 'warehouse', 'LENT'));
        self::assertSame(1_200, new InventoryValue($this->repository)->unitCost('DICE')?->minorUnits);
        self::assertNull($value->unitCost('NONE'));

        [$stock] = $this->costed(CostingMethod::WeightedAverage);
        $stock->returnFromCustomer('2026-0001', 'DICE', 'store', 2, $at->modify('+3 days'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(
            [['DICE', 'store', 2, 2_251]],
            array_map(static fn (ValuedStock $valued): array => [$valued->sku, $valued->location, $valued->units, $valued->value->minorUnits], $value->valuedStock($at->modify('+3 days'))),
        );
        self::assertSame(
            [['DICE', 'warehouse', 4, 4_501]],
            array_map(static fn (ValuedStock $valued): array => [$valued->sku, $valued->location, $valued->units, $valued->value->minorUnits], $value->valuedStock($at->modify('+1 hour'))),
        );
        self::assertSame(
            [['2026-0001', 'DICE', 1, 0, 1_125]],
            array_map(static fn (SaleCost $cost): array => [$cost->reference, $cost->sku, $cost->units, $cost->uncosted, $cost->cost->minorUnits], $value->costOfSales($at, $at->modify('+4 days'))),
        );
        self::assertSame([], $value->costOfSales($at, $at->modify('+1 day')));
        self::assertSame(
            [['2026-0001', 'DICE', 3, 0, 3_376]],
            array_map(static fn (SaleCost $cost): array => [$cost->reference, $cost->sku, $cost->units, $cost->uncosted, $cost->cost->minorUnits], $value->costOfSales($at->modify('+1 day'), $at->modify('+2 days'))),
        );

        $variances = $value->entries($at, $at->modify('+4 days'), [CostEntryKind::Variance]);
        self::assertSame([[-1, -1_125, VarianceReason::Theft, '2026-11-23 15:00']], array_map(static fn (CostEntry $entry): array => [$entry->units, $entry->value()->minorUnits, $entry->reason, $entry->movedAt->format('Y-m-d H:i')], $variances));
        $first = $value->entries($at, $at->modify('+4 days'), limit: 3);
        $next = $value->entries($at, $at->modify('+4 days'), [], $first[2]->id, 3);
        self::assertSame(
            [CostEntryKind::Received, CostEntryKind::Received, CostEntryKind::Landed, CostEntryKind::Received, CostEntryKind::Issued, CostEntryKind::Issued],
            array_map(static fn (CostEntry $entry): CostEntryKind => $entry->kind, [...$first, ...$next]),
        );
        self::assertSame([], $value->entries($at->modify('+5 days'), $at->modify('+6 days')));
    }

    public function testFirstInFirstOutLayersAreReadInTheOrderTheyCame(): void
    {
        $at = new DateTimeImmutable('2026-11-21 10:00:00');
        $this->repository->addLocation(Location::of('warehouse', 'Entrepôt', 10));
        $this->entityManager->flush();
        [$stock] = $this->costed(CostingMethod::FirstInFirstOut);
        $stock->receive('DICE', 'warehouse', 1, $at->modify('+1 day'), 'PO-2', $this->cad(1_300));
        $stock->receive('DICE', 'warehouse', 1, $at, 'PO-1', $this->cad(1_000));
        $stock->receive('DICE', 'warehouse', 1, $at->modify('+2 days'), 'PO-3');

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame([['PO-1', 1_000], ['PO-2', 1_300]], array_map(static fn (CostLayer $layer): array => [$layer->reference, $layer->value()->minorUnits], $this->repository->layersOf('DICE', 'warehouse')));
        self::assertSame([], $this->repository->layersOf('DICE', 'store'));
        // The latest written, whatever the moment it names.
        self::assertSame('PO-1', $this->repository->latestCostedReceipt('DICE')?->reference);

        [$stock] = $this->costed(CostingMethod::FirstInFirstOut);
        $stock->adjustFor('DICE', 'warehouse', -1, VarianceReason::Damaged, $at, 'DMG-1');
        // The layer removed and not flushed yet is no longer found.
        self::assertSame(['PO-2'], array_map(static fn (CostLayer $layer): ?string => $layer->reference, $this->repository->layersOf('DICE', 'warehouse')));
        self::assertSame(['PO-2'], array_map(static fn (CostLayer $layer): ?string => $layer->reference, $this->repository->shopLayersOf('DICE')));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['PO-2'], array_map(static fn (CostLayer $layer): ?string => $layer->reference, $this->repository->shopLayersOf('DICE')));
        self::assertSame([], $this->repository->salesEntriesOf('DMG-1', 'DICE'));
        self::assertSame(
            [[MovementKind::Received, null], [MovementKind::Adjusted, VarianceReason::Damaged], [MovementKind::Received, null], [MovementKind::Received, null]],
            array_map(static fn (StockMovement $movement): array => [$movement->kind, $movement->reason], $this->repository->journal('DICE', 'warehouse')),
        );
    }

    public function testADatabaseOfTheFirstVersionGainsItsNewColumnsAndKeepsItsRows(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('ALTER TABLE phil_commerce_logistics_reservation DROP COLUMN returned');
        $connection->executeStatement('ALTER TABLE phil_commerce_logistics_purchase_order DROP COLUMN line_terms');
        $connection->executeStatement('ALTER TABLE phil_commerce_logistics_stock_movement DROP COLUMN reason');
        $connection->executeStatement('DROP TABLE phil_commerce_logistics_cost_layer');
        $connection->executeStatement('DROP TABLE phil_commerce_logistics_cost_entry');
        $connection->insert('phil_commerce_logistics_location', ['code' => 'warehouse', 'name' => 'Entrepôt', 'priority' => 0, 'active' => 1], ['active' => 'boolean']);
        $connection->insert('phil_commerce_logistics_reservation', ['id' => '019a0000-0000-7000-8000-000000000001', 'order_number' => '2026-0001', 'sku' => 'DICE', 'location' => 'warehouse', 'quantity' => 3, 'shipped' => 3, 'state' => 'shipped']);
        $connection->insert('phil_commerce_logistics_purchase_order', ['number' => 'PO-1', 'supplier' => 'Dice Works', 'location' => 'warehouse', 'ordered' => '{"DICE":4}', 'received' => '[]', 'state' => 'open', 'ordered_at' => '2026-11-01 10:00:00', 'expected_on' => null]);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaManager = $connection->createSchemaManager();
        $comparator = $schemaManager->createComparator();
        $diffs = [];
        $created = [];

        foreach (new SchemaTool($this->entityManager)->getSchemaFromMetadata($metadata)->getTables() as $table) {
            $name = $table->getObjectName()->toString();

            if (!$schemaManager->tableExists($name)) {
                $created[] = $name;

                foreach ($connection->getDatabasePlatform()->getCreateTableSQL($table) as $statement) {
                    $connection->executeStatement($statement);
                }

                continue;
            }

            $diffs[$name] = $comparator->compareTables($schemaManager->introspectTableByUnquotedName($name), $table);
        }

        sort($created);
        self::assertSame(['phil_commerce_logistics_cost_entry', 'phil_commerce_logistics_cost_layer'], $created);
        self::assertSame(
            ['phil_commerce_logistics_purchase_order' => [['line_terms'], 0, 0, 0, 0], 'phil_commerce_logistics_reservation' => [['returned'], 0, 0, 0, 0], 'phil_commerce_logistics_stock_movement' => [['reason'], 0, 0, 0, 0]],
            $this->alterations($diffs),
        );

        foreach ($diffs as $diff) {
            foreach ($connection->getDatabasePlatform()->getAlterTableSQL($diff) as $statement) {
                $connection->executeStatement($statement);
            }
        }

        $reservation = $this->repository->reservationsOf('2026-0001')[0] ?? null;
        self::assertInstanceOf(Reservation::class, $reservation);
        self::assertSame([3, 0, 3], [$reservation->shipped(), $reservation->returned(), $reservation->returnable()]);
        $line = $this->repository->findPurchaseOrder('PO-1')?->line('DICE');
        self::assertSame([4, null, null], [$line?->units, $line?->unitCost, $line?->owner]);

        new Stock($this->repository, $this->repository)->returnFromCustomer('2026-0001', 'DICE', 'warehouse', 2, new DateTimeImmutable('2026-11-21 10:00:00'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame([2, 2], [$this->repository->reservationsOf('2026-0001')[0]->returned(), $this->repository->level('DICE', 'warehouse')?->onHand()]);
    }

    /**
     * @param list<string|null> $values
     *
     * @return list<string|null>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    /**
     * @return array{Stock, Purchasing} services on the repository, costing by this method in Canadian dollars
     */
    private function costed(CostingMethod $method): array
    {
        $costing = new Costing($this->repository, $method, Currency::of('CAD'));
        $stock = new Stock($this->repository, $this->repository, costing: $costing);

        return [$stock, new Purchasing($this->repository, $this->repository, $stock, $costing)];
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    /**
     * @return list<array{string|null, int, int}> the owner, units and value of the layers of these SKUs at a location
     */
    private function layersOf(string $first, string $location, string $second): array
    {
        return array_map(
            static fn (CostLayer $layer): array => [$layer->owner, $layer->units(), $layer->value()->minorUnits],
            [...$this->repository->layersOf($first, $location), ...$this->repository->layersOf($second, $location)],
        );
    }

    /**
     * @param array<string, int> $products SKU → units
     */
    private function order(string $number, array $products): Order
    {
        $price = $this->cad(2_000);
        $order = Order::open($number, Currency::of('CAD'));

        foreach ($products as $sku => $quantity) {
            $order->add(Sellable::of(SellableReference::of('product', $sku), $sku, $price, 'goods'), $quantity, $price);
        }

        $order->place(new DateTimeImmutable('2026-11-21 10:00:00'));

        return $order;
    }

    /**
     * @param array<string, TableDiff> $tables table → how the database differs from the mapping
     *
     * @return array<string, array{list<string>, int, int, int, int}> each table that differs → its columns added, and
     *                                                                how many columns changed or dropped, and how many
     *                                                                indexes added or dropped
     */
    private function alterations(array $tables): array
    {
        $alterations = [];

        foreach ($tables as $name => $table) {
            if ($table->isEmpty()) {
                continue;
            }

            $alterations[$name] = [
                array_map(static fn (Column $column): string => $column->getObjectName()->toString(), array_values($table->getAddedColumns())),
                \count($table->getChangedColumns()),
                \count($table->getDroppedColumns()),
                \count($table->getAddedIndexes()),
                \count($table->getDroppedIndexes()),
            ];
        }

        ksort($alterations);

        return $alterations;
    }
}
