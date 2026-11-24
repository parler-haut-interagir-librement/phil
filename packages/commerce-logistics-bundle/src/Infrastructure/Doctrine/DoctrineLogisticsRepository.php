<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceLogisticsBundle\Domain\Contract\CostRepository;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostEntryKind;
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
use Phil\CommerceLogisticsBundle\Domain\Model\WaveAssignment;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseState;
use Phil\CommerceLogisticsBundle\Domain\Repository\ConventionRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\DeliveryMethodRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\LocationRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\PurchaseOrderRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\SerialNumberRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShipmentRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShippingCostRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShippingDocumentRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShippingWaveRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockPolicyRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommerceLogisticsBundle\Domain\Serial\SerialState;
use SortDirection;

/**
 * The logistics repositories on the Doctrine ORM: locations, stock levels keyed by SKU and location, the stock
 * journal, reservations, stock policies, delivery methods, shipments and their costs, conventions and print jobs,
 * purchase orders, serial numbers, shipping documents and waves, cost layers and the cost journal, each in a table of
 * its own.
 *
 * Cost layers and entries given to it and not flushed yet are found as if they were stored, and a layer removed and not
 * flushed yet is no longer found: the costing reads, within one unit of work, what it wrote earlier in it.
 */
final readonly class DoctrineLogisticsRepository implements CostRepository, LocationRepository, StockRepository, StockPolicyRepository, DeliveryMethodRepository, ShipmentRepository, ShippingCostRepository, ConventionRepository, PurchaseOrderRepository, SerialNumberRepository, ShippingDocumentRepository, ShippingWaveRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findLocation(string $code): ?Location
    {
        return $this->entityManager->find(Location::class, $code);
    }

    #[Override]
    public function openLocations(): array
    {
        return $this->entityManager->getRepository(Location::class)->findBy(['active' => true], ['priority' => 'DESC', 'code' => 'ASC']);
    }

    #[Override]
    public function addLocation(Location $location): void
    {
        $this->entityManager->persist($location);
    }

    #[Override]
    public function level(string $sku, string $location): ?StockLevel
    {
        return $this->entityManager->find(StockLevel::class, ['sku' => $sku, 'location' => $location]);
    }

    #[Override]
    public function levelsOf(string $sku): array
    {
        return $this->entityManager->getRepository(StockLevel::class)->findBy(['sku' => $sku]);
    }

    #[Override]
    public function levelsOfSkus(array $skus): array
    {
        return $this->entityManager->getRepository(StockLevel::class)->findBy(['sku' => $skus]);
    }

    #[Override]
    public function addLevel(StockLevel $level): void
    {
        $this->entityManager->persist($level);
    }

    #[Override]
    public function record(StockMovement $movement): void
    {
        $this->entityManager->persist($movement);
    }

    #[Override]
    public function journal(string $sku, string $location): array
    {
        return $this->entityManager->getRepository(StockMovement::class)->findBy(['sku' => $sku, 'location' => $location], ['movedAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function reservationsOf(string $orderNumber): array
    {
        return $this->entityManager->getRepository(Reservation::class)->findBy(['orderNumber' => $orderNumber], ['id' => 'ASC']);
    }

    #[Override]
    public function addReservation(Reservation $reservation): void
    {
        $this->entityManager->persist($reservation);
    }

    #[Override]
    public function findMethod(string $code): ?DeliveryMethod
    {
        return $this->entityManager->find(DeliveryMethod::class, $code);
    }

    #[Override]
    public function activeMethods(): array
    {
        return $this->entityManager->getRepository(DeliveryMethod::class)->findBy(['active' => true], ['priority' => 'DESC', 'code' => 'ASC']);
    }

    #[Override]
    public function addMethod(DeliveryMethod $method): void
    {
        $this->entityManager->persist($method);
    }

    #[Override]
    public function addShipment(Shipment $shipment): void
    {
        $this->entityManager->persist($shipment);
    }

    #[Override]
    public function shipmentsOf(string $orderNumber): array
    {
        return $this->entityManager->getRepository(Shipment::class)->findBy(['orderNumber' => $orderNumber], ['shippedAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function addCost(ShippingCost $cost): void
    {
        $this->entityManager->persist($cost);
    }

    #[Override]
    public function costOf(string $shipment): ?ShippingCost
    {
        return $this->entityManager->find(ShippingCost::class, $shipment);
    }

    #[Override]
    public function costsOf(string $orderNumber): array
    {
        return $this->entityManager->getRepository(ShippingCost::class)->findBy(['orderNumber' => $orderNumber], ['recordedAt' => 'ASC', 'shipment' => 'ASC']);
    }

    #[Override]
    public function policy(string $sku): ?StockPolicy
    {
        return $this->entityManager->find(StockPolicy::class, $sku);
    }

    #[Override]
    public function policiesOf(array $skus): array
    {
        return [] === $skus ? [] : $this->entityManager->getRepository(StockPolicy::class)->findBy(['sku' => $skus]);
    }

    #[Override]
    public function addPolicy(StockPolicy $policy): void
    {
        $this->entityManager->persist($policy);
    }

    #[Override]
    public function findConvention(string $code): ?Convention
    {
        return $this->entityManager->find(Convention::class, $code);
    }

    #[Override]
    public function addConvention(Convention $convention): void
    {
        $this->entityManager->persist($convention);
    }

    #[Override]
    public function conventionsMeeting(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        /** @var list<Convention> */
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Convention::class, 'c')
            ->where('c.startsOn <= :until')
            ->andWhere('c.endsOn >= :from')
            ->orderBy('c.startsOn', SortDirection::Ascending)
            ->addOrderBy('c.code', SortDirection::Ascending)
            ->setParameter('until', $until, 'date_immutable')
            ->setParameter('from', $from, 'date_immutable')
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function quantity(string $convention, string $sku): ?ConventionQuantity
    {
        return $this->entityManager->find(ConventionQuantity::class, ['convention' => $convention, 'sku' => $sku]);
    }

    #[Override]
    public function quantitiesOf(string $convention): array
    {
        return $this->entityManager->getRepository(ConventionQuantity::class)->findBy(['convention' => $convention], ['sku' => 'ASC']);
    }

    #[Override]
    public function addQuantity(ConventionQuantity $quantity): void
    {
        $this->entityManager->persist($quantity);
    }

    #[Override]
    public function conventionSkus(array $skus): array
    {
        /** @var list<array{sku: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT q.sku')
            ->from(ConventionQuantity::class, 'q')
            ->where('q.sku IN (:skus)')
            ->orderBy('q.sku', SortDirection::Ascending)
            ->setParameter('skus', $skus)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'sku');
    }

    #[Override]
    public function printJobsOf(string $orderNumber): array
    {
        return $this->entityManager->getRepository(PrintJob::class)->findBy(['orderNumber' => $orderNumber], ['id' => 'ASC']);
    }

    #[Override]
    public function addPrintJob(PrintJob $job): void
    {
        $this->entityManager->persist($job);
    }

    #[Override]
    public function findPurchaseOrder(string $number): ?PurchaseOrder
    {
        return $this->entityManager->find(PurchaseOrder::class, $number);
    }

    #[Override]
    public function addPurchaseOrder(PurchaseOrder $order): void
    {
        $this->entityManager->persist($order);
    }

    #[Override]
    public function openPurchaseOrders(): array
    {
        return $this->entityManager->getRepository(PurchaseOrder::class)->findBy(['state' => PurchaseState::Open], ['orderedAt' => 'ASC', 'number' => 'ASC']);
    }

    #[Override]
    public function findSerial(string $sku, string $serial): ?SerialNumber
    {
        return $this->entityManager->find(SerialNumber::class, ['sku' => $sku, 'serial' => $serial]);
    }

    #[Override]
    public function addSerial(SerialNumber $serial): void
    {
        $this->entityManager->persist($serial);
    }

    #[Override]
    public function serialsInStock(string $sku, string $location): int
    {
        return $this->entityManager->getRepository(SerialNumber::class)->count(['sku' => $sku, 'location' => $location, 'state' => SerialState::InStock]);
    }

    #[Override]
    public function serialsOfOrder(string $orderNumber): array
    {
        return $this->entityManager->getRepository(SerialNumber::class)->findBy(['orderNumber' => $orderNumber], ['sku' => 'ASC', 'serial' => 'ASC']);
    }

    #[Override]
    public function findDocument(ShippingDocumentKind $kind, string $orderNumber, ?string $shipment): ?ShippingDocument
    {
        return $this->entityManager->getRepository(ShippingDocument::class)->findOneBy(['kind' => $kind, 'orderNumber' => $orderNumber, 'shipmentId' => $shipment ?? '']);
    }

    #[Override]
    public function addDocument(ShippingDocument $document): void
    {
        $this->entityManager->persist($document);
    }

    #[Override]
    public function findWave(string $code): ?ShippingWave
    {
        return $this->entityManager->find(ShippingWave::class, $code);
    }

    #[Override]
    public function addWave(ShippingWave $wave): void
    {
        $this->entityManager->persist($wave);
    }

    #[Override]
    public function plannedWaves(): array
    {
        return $this->entityManager->getRepository(ShippingWave::class)->findBy(['releasedAt' => null], ['estimatedOn' => 'ASC', 'code' => 'ASC']);
    }

    #[Override]
    public function assignmentOf(string $orderNumber): ?WaveAssignment
    {
        return $this->entityManager->find(WaveAssignment::class, $orderNumber);
    }

    #[Override]
    public function addAssignment(WaveAssignment $assignment): void
    {
        $this->entityManager->persist($assignment);
    }

    #[Override]
    public function removeAssignment(WaveAssignment $assignment): void
    {
        $this->entityManager->remove($assignment);
    }

    #[Override]
    public function ordersOfWave(string $code): array
    {
        return array_map(
            static fn (WaveAssignment $assignment): string => $assignment->orderNumber,
            $this->entityManager->getRepository(WaveAssignment::class)->findBy(['wave' => $code], ['orderNumber' => 'ASC']),
        );
    }

    #[Override]
    public function layersOf(string $sku, string $location): array
    {
        $layers = $this->withPending(
            CostLayer::class,
            $this->entityManager->getRepository(CostLayer::class)->findBy(['sku' => $sku, 'location' => $location]),
            static fn (CostLayer $layer): bool => $layer->sku === $sku && $layer->location === $location,
        );
        usort($layers, static fn (CostLayer $left, CostLayer $right): int => [$left->receivedAt, $left->id] <=> [$right->receivedAt, $right->id]);

        return $layers;
    }

    #[Override]
    public function shopLayersOf(string $sku): array
    {
        return $this->withPending(
            CostLayer::class,
            $this->entityManager->getRepository(CostLayer::class)->findBy(['sku' => $sku, 'owner' => null]),
            static fn (CostLayer $layer): bool => $layer->sku === $sku && $layer->isShops(),
        );
    }

    #[Override]
    public function addLayer(CostLayer $layer): void
    {
        $this->entityManager->persist($layer);
    }

    #[Override]
    public function removeLayer(CostLayer $layer): void
    {
        $this->entityManager->remove($layer);
    }

    #[Override]
    public function addEntry(CostEntry $entry): void
    {
        $this->entityManager->persist($entry);
    }

    #[Override]
    public function salesEntriesOf(string $reference, string $sku): array
    {
        $kinds = [CostEntryKind::Issued, CostEntryKind::Returned];
        $entries = $this->withPending(
            CostEntry::class,
            $this->entityManager->getRepository(CostEntry::class)->findBy(['reference' => $reference, 'sku' => $sku, 'kind' => $kinds]),
            static fn (CostEntry $entry): bool => $entry->reference === $reference && $entry->sku === $sku && \in_array($entry->kind, $kinds, true),
        );
        usort($entries, static fn (CostEntry $left, CostEntry $right): int => $left->id <=> $right->id);

        return $entries;
    }

    #[Override]
    public function latestCostedReceipt(string $sku): ?CostEntry
    {
        /** @var list<CostEntry> $stored */
        $stored = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(CostEntry::class, 'e')
            ->where('e.sku = :sku')
            ->andWhere('e.kind = :kind')
            ->andWhere('e.owner IS NULL')
            ->andWhere('e.units > e.uncosted')
            ->orderBy('e.id', SortDirection::Descending)
            ->setParameter('sku', $sku)
            ->setParameter('kind', CostEntryKind::Received)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
        $entries = $this->withPending(
            CostEntry::class,
            $stored,
            static fn (CostEntry $entry): bool => $entry->sku === $sku && CostEntryKind::Received === $entry->kind && null === $entry->owner && $entry->costedUnits() > 0,
        );
        usort($entries, static fn (CostEntry $left, CostEntry $right): int => $right->id <=> $left->id);

        return $entries[0] ?? null;
    }

    #[Override]
    public function valuation(DateTimeImmutable $at): array
    {
        /** @var list<array{sku: string, location: string, currency: string, units: int|string, value: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('e.sku', 'e.location', 'e.currency', 'SUM(e.units - e.uncosted) AS units', 'SUM(e.value) AS value')
            ->from(CostEntry::class, 'e')
            ->where('e.owner IS NULL')
            ->andWhere('e.movedAt <= :at')
            ->groupBy('e.sku', 'e.location', 'e.currency')
            ->orderBy('e.sku', SortDirection::Ascending)
            ->addOrderBy('e.location', SortDirection::Ascending)
            ->addOrderBy('e.currency', SortDirection::Ascending)
            ->setParameter('at', $at->setTimezone(new DateTimeZone('UTC')), 'datetime_immutable')
            ->getQuery()
            ->getArrayResult();
        $valued = [];

        foreach ($rows as $row) {
            if (0 !== (int) $row['units'] || 0 !== (int) $row['value']) {
                $valued[] = ['sku' => $row['sku'], 'location' => $row['location'], 'currency' => $row['currency'], 'units' => (int) $row['units'], 'value' => (int) $row['value']];
            }
        }

        return $valued;
    }

    #[Override]
    public function salesCost(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        /** @var list<array{reference: string, sku: string, currency: string, units: int|string, uncosted: int|string, value: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('e.reference', 'e.sku', 'e.currency', 'SUM(e.units) AS units', 'SUM(e.uncosted) AS uncosted', 'SUM(e.value) AS value')
            ->from(CostEntry::class, 'e')
            ->where('e.owner IS NULL')
            ->andWhere('e.reference IS NOT NULL')
            ->andWhere('e.kind IN (:kinds)')
            ->andWhere('e.movedAt >= :from')
            ->andWhere('e.movedAt < :until')
            ->groupBy('e.reference', 'e.sku', 'e.currency')
            ->orderBy('e.reference', SortDirection::Ascending)
            ->addOrderBy('e.sku', SortDirection::Ascending)
            ->addOrderBy('e.currency', SortDirection::Ascending)
            ->setParameter('kinds', [CostEntryKind::Issued->value, CostEntryKind::Returned->value])
            ->setParameter('from', $from->setTimezone(new DateTimeZone('UTC')), 'datetime_immutable')
            ->setParameter('until', $until->setTimezone(new DateTimeZone('UTC')), 'datetime_immutable')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['reference' => $row['reference'], 'sku' => $row['sku'], 'currency' => $row['currency'], 'units' => (int) $row['units'], 'uncosted' => (int) $row['uncosted'], 'value' => (int) $row['value']], $rows);
    }

    #[Override]
    public function entries(DateTimeImmutable $from, DateTimeImmutable $until, array $kinds, ?string $after, int $limit): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(CostEntry::class, 'e')
            ->where('e.movedAt >= :from')
            ->andWhere('e.movedAt < :until')
            ->orderBy('e.id', SortDirection::Ascending)
            ->setParameter('from', $from->setTimezone(new DateTimeZone('UTC')), 'datetime_immutable')
            ->setParameter('until', $until->setTimezone(new DateTimeZone('UTC')), 'datetime_immutable')
            ->setMaxResults($limit);

        if ([] !== $kinds) {
            $query->andWhere('e.kind IN (:kinds)')->setParameter('kinds', array_map(static fn (CostEntryKind $kind): string => $kind->value, $kinds));
        }

        if (null !== $after) {
            $query->andWhere('e.id > :after')->setParameter('after', $after);
        }

        /** @var list<CostEntry> */
        return $query->getQuery()->getResult();
    }

    /**
     * What a query found, less what the unit of work will delete, plus what it will insert that matches.
     *
     * @template T of object
     *
     * @param class-string<T>   $class
     * @param list<T>           $stored
     * @param callable(T): bool $matches
     *
     * @return list<T>
     */
    private function withPending(string $class, array $stored, callable $matches): array
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $found = array_values(array_filter($stored, static fn (object $entity): bool => !$unitOfWork->isScheduledForDelete($entity)));

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof $class && $matches($entity)) {
                $found[] = $entity;
            }
        }

        return $found;
    }
}
