<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceLogisticsBundle\Application\Purchasing;
use Phil\CommerceLogisticsBundle\Application\SerialNumbers;
use Phil\CommerceLogisticsBundle\Application\ShippingWaves;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Document\ShippingDocumentKind;
use Phil\CommerceLogisticsBundle\Domain\Model\Convention;
use Phil\CommerceLogisticsBundle\Domain\Model\ConventionQuantity;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingDocument;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingWave;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseLine;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
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

    public function testADatabaseOfTheFirstVersionGainsItsNewColumnsAndKeepsItsRows(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('ALTER TABLE phil_commerce_logistics_reservation DROP COLUMN returned');
        $connection->executeStatement('ALTER TABLE phil_commerce_logistics_purchase_order DROP COLUMN line_terms');
        $connection->insert('phil_commerce_logistics_location', ['code' => 'warehouse', 'name' => 'Entrepôt', 'priority' => 0, 'active' => 1], ['active' => 'boolean']);
        $connection->insert('phil_commerce_logistics_reservation', ['id' => '019a0000-0000-7000-8000-000000000001', 'order_number' => '2026-0001', 'sku' => 'DICE', 'location' => 'warehouse', 'quantity' => 3, 'shipped' => 3, 'state' => 'shipped']);
        $connection->insert('phil_commerce_logistics_purchase_order', ['number' => 'PO-1', 'supplier' => 'Dice Works', 'location' => 'warehouse', 'ordered' => '{"DICE":4}', 'received' => '[]', 'state' => 'open', 'ordered_at' => '2026-11-01 10:00:00', 'expected_on' => null]);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaManager = $connection->createSchemaManager();
        $comparator = $schemaManager->createComparator();
        $diffs = [];

        foreach (new SchemaTool($this->entityManager)->getSchemaFromMetadata($metadata)->getTables() as $table) {
            $name = $table->getObjectName()->toString();
            \assert('' !== $name);
            $diffs[$name] = $comparator->compareTables($schemaManager->introspectTableByUnquotedName($name), $table);
        }

        self::assertSame(
            ['phil_commerce_logistics_purchase_order' => [['line_terms'], 0, 0, 0, 0], 'phil_commerce_logistics_reservation' => [['returned'], 0, 0, 0, 0]],
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
