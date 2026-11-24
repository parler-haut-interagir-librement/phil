<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\CommerceConsignmentBundle\Domain\Contract\LedgerRepository;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;

/**
 * The layers and the ledger in memory, for tests and for an application that keeps them elsewhere and loads them for
 * one operation. It keeps what it is given until it is dropped: never share one between requests.
 */
final class InMemoryLedgerRepository implements LedgerRepository
{
    /** @var array<string, StockLayer> */
    private array $layers = [];

    /** @var list<LedgerEntry> */
    private array $entries = [];

    #[Override]
    public function layer(string $id): ?StockLayer
    {
        return $this->layers[$id] ?? null;
    }

    #[Override]
    public function layersAt(string $sku, string $location): array
    {
        return $this->filter(static fn (StockLayer $layer): bool => $layer->sku === $sku && $layer->location === $location && $layer->remaining() > 0);
    }

    #[Override]
    public function layersOf(string $sku): array
    {
        return $this->filter(static fn (StockLayer $layer): bool => $layer->sku === $sku && $layer->remaining() > 0);
    }

    #[Override]
    public function layersFrom(string $origin): array
    {
        // The layers of one receipt share its instant, and are ordered by their identifiers, which grow with each new
        // layer: the order they were added in.
        return $this->filter(static fn (StockLayer $layer): bool => $layer->origin === $origin);
    }

    #[Override]
    public function latestLayer(string $sku, string $consignor): ?StockLayer
    {
        $layers = $this->filter(static fn (StockLayer $layer): bool => $layer->sku === $sku && $layer->owner()->key() === $consignor);
        usort($layers, IssueOrder::age(...));

        return [] === $layers ? null : $layers[\count($layers) - 1];
    }

    #[Override]
    public function addLayer(StockLayer $layer): void
    {
        $this->layers[$layer->id] = $layer;
    }

    #[Override]
    public function record(LedgerEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    #[Override]
    public function entriesOf(string $reference, string $sku): array
    {
        return array_values(array_filter($this->entries, static fn (LedgerEntry $entry): bool => $entry->reference === $reference && $entry->sku === $sku));
    }

    #[Override]
    public function journal(string $sku, int $limit = 500): array
    {
        return \array_slice(array_filter($this->entries, static fn (LedgerEntry $entry): bool => $entry->sku === $sku), 0, max(1, $limit));
    }

    #[Override]
    public function consignorEntries(string $consignor, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array
    {
        $entries = array_filter($this->entries, static fn (LedgerEntry $entry): bool => $entry->owner()->key() === $consignor && $entry->movedAt() >= $from && $entry->movedAt() < $to);

        return \array_slice($this->chronological($entries), 0, max(1, $limit));
    }

    #[Override]
    public function heldBy(string $consignor, DateTimeImmutable $before, int $limit): array
    {
        $held = [];

        foreach ($this->entries as $entry) {
            if ($entry->owner()->key() === $consignor && $entry->movedAt() < $before) {
                $held[$entry->layer] = ($held[$entry->layer] ?? 0) + $entry->quantity;
            }
        }

        // A layer's first entry is written when it is added, and its identifier grows with each new layer: the layers
        // come in the order of their identifiers.
        $held = array_filter($held, static fn (int $units): bool => $units > 0);

        return \array_slice($held, 0, max(1, $limit), true);
    }

    #[Override]
    public function owedTo(string $consignor, string $currency): int
    {
        $owed = 0;

        foreach ($this->entries as $entry) {
            $margin = $entry->margin();

            if (null !== $margin && $entry->owner()->key() === $consignor && $margin->cost->currency->code === $currency) {
                $owed += $margin->cost->minorUnits;
            }
        }

        return $owed;
    }

    #[Override]
    public function consignedLayers(?string $consignor, int $limit): array
    {
        $layers = $this->filter(static fn (StockLayer $layer): bool => $layer->remaining() > 0 && $layer->owner()->isConsigned() && (null === $consignor || $layer->owner()->key() === $consignor));
        usort($layers, IssueOrder::age(...));

        return \array_slice($layers, 0, max(1, $limit));
    }

    /**
     * @param array<array-key, LedgerEntry> $entries
     *
     * @return list<LedgerEntry>
     */
    private function chronological(array $entries): array
    {
        usort($entries, static fn (LedgerEntry $left, LedgerEntry $right): int => [$left->movedAt()->format('U.u'), $left->id] <=> [$right->movedAt()->format('U.u'), $right->id]);

        return $entries;
    }

    /**
     * @param callable(StockLayer): bool $keep
     *
     * @return list<StockLayer>
     */
    private function filter(callable $keep): array
    {
        return array_values(array_filter($this->layers, $keep));
    }
}
