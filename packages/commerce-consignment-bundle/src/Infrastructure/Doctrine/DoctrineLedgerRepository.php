<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceConsignmentBundle\Domain\Contract\LedgerRepository;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;

/**
 * The layers and the ledger in two tables, `phil_commerce_consignment_layer` and `phil_commerce_consignment_entry`.
 *
 * Nothing is flushed here: the caller commits the ledger with the stock it moved. Each lookup loads its rows, then reads
 * the entity manager's identity map, so that what this request persisted or changed and has not flushed yet counts —
 * a receipt and a sale in one request, two sales of one order. The sums — what a consignor holds or is owed — add the
 * entries persisted and not flushed yet to the rows.
 */
final readonly class DoctrineLedgerRepository implements LedgerRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function layer(string $id): ?StockLayer
    {
        return $this->entityManager->find(StockLayer::class, $id);
    }

    #[Override]
    public function layersAt(string $sku, string $location): array
    {
        $this->entityManager->createQueryBuilder()
            ->select('layer')
            ->from(StockLayer::class, 'layer')
            ->where('layer.sku = :sku AND layer.location = :location AND layer.remaining > 0')
            ->setParameter('sku', $sku)
            ->setParameter('location', $location)
            ->getQuery()
            ->getResult();

        return $this->managedLayers(static fn (StockLayer $layer): bool => $layer->sku === $sku && $layer->location === $location && $layer->remaining() > 0);
    }

    #[Override]
    public function layersOf(string $sku): array
    {
        $this->entityManager->createQueryBuilder()
            ->select('layer')
            ->from(StockLayer::class, 'layer')
            ->where('layer.sku = :sku AND layer.remaining > 0')
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getResult();

        return $this->managedLayers(static fn (StockLayer $layer): bool => $layer->sku === $sku && $layer->remaining() > 0);
    }

    #[Override]
    public function layersFrom(string $origin): array
    {
        $this->entityManager->getRepository(StockLayer::class)->findBy(['origin' => $origin]);
        $layers = $this->managedLayers(static fn (StockLayer $layer): bool => $layer->origin === $origin);
        usort($layers, IssueOrder::age(...));

        return $layers;
    }

    #[Override]
    public function latestLayer(string $sku, string $consignor): ?StockLayer
    {
        $this->entityManager->getRepository(StockLayer::class)->findOneBy(['sku' => $sku, 'consignor' => $consignor], ['receivedAt' => 'DESC', 'origin' => 'DESC', 'id' => 'DESC']);
        $layers = $this->managedLayers(static fn (StockLayer $layer): bool => $layer->sku === $sku && $layer->owner()->key() === $consignor);
        usort($layers, IssueOrder::age(...));

        return [] === $layers ? null : $layers[\count($layers) - 1];
    }

    #[Override]
    public function addLayer(StockLayer $layer): void
    {
        $this->entityManager->persist($layer);
    }

    #[Override]
    public function record(LedgerEntry $entry): void
    {
        $this->entityManager->persist($entry);
    }

    #[Override]
    public function entriesOf(string $reference, string $sku): array
    {
        $this->entityManager->getRepository(LedgerEntry::class)->findBy(['reference' => $reference, 'sku' => $sku]);
        $entries = [];

        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap()[LedgerEntry::class] ?? [] as $entry) {
            if ($entry instanceof LedgerEntry && $entry->reference === $reference && $entry->sku === $sku) {
                $entries[] = $entry;
            }
        }

        usort($entries, static fn (LedgerEntry $left, LedgerEntry $right): int => [$left->movedAt()->format('U.u'), $left->id] <=> [$right->movedAt()->format('U.u'), $right->id]);

        return $entries;
    }

    #[Override]
    public function journal(string $sku, int $limit = 500): array
    {
        /** @var list<LedgerEntry> */
        return $this->entityManager->getRepository(LedgerEntry::class)->findBy(['sku' => $sku], ['movedAt' => 'ASC', 'id' => 'ASC'], max(1, $limit));
    }

    #[Override]
    public function consignorEntries(string $consignor, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array
    {
        /** @var list<LedgerEntry> $entries */
        $entries = $this->entityManager->createQueryBuilder()
            ->select('entry')
            ->from(LedgerEntry::class, 'entry')
            ->where('entry.consignor = :consignor AND entry.movedAt >= :from AND entry.movedAt < :to')
            ->orderBy('entry.movedAt', 'ASC')
            ->addOrderBy('entry.id', 'ASC')
            ->setParameter('consignor', $consignor)
            ->setParameter('from', $from, 'datetime_utc_immutable')
            ->setParameter('to', $to, 'datetime_utc_immutable')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        foreach ($this->pending(LedgerEntry::class) as $entry) {
            if ($entry->owner()->key() === $consignor && $entry->movedAt() >= $from && $entry->movedAt() < $to) {
                $entries[] = $entry;
            }
        }

        usort($entries, static fn (LedgerEntry $left, LedgerEntry $right): int => [$left->movedAt()->format('U.u'), $left->id] <=> [$right->movedAt()->format('U.u'), $right->id]);

        return \array_slice($entries, 0, max(1, $limit));
    }

    #[Override]
    public function heldBy(string $consignor, DateTimeImmutable $before, int $limit): array
    {
        /** @var list<array{layer: string, units: int|numeric-string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('entry.layer AS layer, SUM(entry.quantity) AS units')
            ->from(LedgerEntry::class, 'entry')
            ->where('entry.consignor = :consignor AND entry.movedAt < :before')
            ->groupBy('entry.layer')
            ->setParameter('consignor', $consignor)
            ->setParameter('before', $before, 'datetime_utc_immutable')
            ->getQuery()
            ->getArrayResult();

        $held = [];

        foreach ($rows as $row) {
            $held[$row['layer']] = (int) $row['units'];
        }

        foreach ($this->pending(LedgerEntry::class) as $entry) {
            if ($entry->owner()->key() === $consignor && $entry->movedAt() < $before) {
                $held[$entry->layer] = ($held[$entry->layer] ?? 0) + $entry->quantity;
            }
        }

        $held = array_filter($held, static fn (int $units): bool => $units > 0);
        ksort($held, \SORT_STRING);

        return \array_slice($held, 0, max(1, $limit), true);
    }

    #[Override]
    public function owedTo(string $consignor, string $currency): int
    {
        $owed = (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(entry.cost), 0)')
            ->from(LedgerEntry::class, 'entry')
            ->where('entry.consignor = :consignor AND entry.currency = :currency AND entry.cost IS NOT NULL')
            ->setParameter('consignor', $consignor)
            ->setParameter('currency', $currency)
            ->getQuery()
            ->getSingleScalarResult();

        foreach ($this->pending(LedgerEntry::class) as $entry) {
            $cost = $entry->margin()?->cost;

            if (null !== $cost && $entry->owner()->key() === $consignor && $cost->currency->code === $currency) {
                $owed += $cost->minorUnits;
            }
        }

        return $owed;
    }

    #[Override]
    public function consignedLayers(?string $consignor, int $limit): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('layer')
            ->from(StockLayer::class, 'layer')
            ->where("layer.remaining > 0 AND layer.consignor <> ''")
            ->orderBy('layer.receivedAt', 'ASC')
            ->addOrderBy('layer.origin', 'ASC')
            ->addOrderBy('layer.id', 'ASC')
            ->setMaxResults(max(1, $limit));

        if (null !== $consignor) {
            $query->andWhere('layer.consignor = :consignor')->setParameter('consignor', $consignor);
        }

        $query->getQuery()->getResult();
        $layers = $this->managedLayers(static fn (StockLayer $layer): bool => $layer->remaining() > 0 && $layer->owner()->isConsigned() && (null === $consignor || $layer->owner()->key() === $consignor));
        usort($layers, IssueOrder::age(...));

        return \array_slice($layers, 0, max(1, $limit));
    }

    /**
     * The entities of a class persisted and not flushed yet.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function pending(string $class): array
    {
        return array_values(array_filter($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions(), static fn (object $entity): bool => $entity instanceof $class));
    }

    /**
     * @param callable(StockLayer): bool $keep
     *
     * @return list<StockLayer>
     */
    private function managedLayers(callable $keep): array
    {
        $layers = [];

        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap()[StockLayer::class] ?? [] as $layer) {
            if ($layer instanceof StockLayer && $keep($layer)) {
                $layers[] = $layer;
            }
        }

        return $layers;
    }
}
