<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;

/**
 * Finds and stores the layers of stock and the ledger's entries. `Infrastructure\Doctrine\DoctrineLedgerRepository` is
 * the Doctrine adapter, `Infrastructure\Memory\InMemoryLedgerRepository` the one in memory.
 */
interface LedgerRepository
{
    public function layer(string $id): ?StockLayer;

    /**
     * @return list<StockLayer> the layers of the SKU at the location that still hold units, in no particular order
     */
    public function layersAt(string $sku, string $location): array;

    /**
     * @return list<StockLayer> every layer of the SKU that still holds units, at every location, in no particular order
     */
    public function layersOf(string $sku): array;

    /**
     * @return list<StockLayer> every layer split from the receipt layer `$origin`, itself included, emptied ones too
     */
    public function layersFrom(string $origin): array;

    /**
     * The layer an owner received last of the SKU, at any location, emptied or not; null when the owner never held it.
     */
    public function latestLayer(string $sku, string $consignor): ?StockLayer;

    public function addLayer(StockLayer $layer): void;

    public function record(LedgerEntry $entry): void;

    /**
     * @return list<LedgerEntry> the entries of a reference for the SKU, oldest first
     */
    public function entriesOf(string $reference, string $sku): array;

    /**
     * @return list<LedgerEntry> the entries of the SKU at every location, oldest first, at most `$limit`
     */
    public function journal(string $sku, int $limit = 500): array;

    /**
     * @return list<LedgerEntry> the entries of a consignor moved from `$from` included to `$to` excluded, oldest first, at
     *                           most `$limit`
     */
    public function consignorEntries(string $consignor, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array;

    /**
     * What each layer of a consignor held just before the instant, summed from its entries: the layers that held none are
     * left out, the others ordered by identifier, at most `$limit`.
     *
     * @return array<string, int> layer → units
     */
    public function heldBy(string $consignor, DateTimeImmutable $before, int $limit): array;

    /**
     * What the shop owes a consignor in a currency over the whole ledger, in minor units: the shares of the units sold
     * and bought out, less those brought back.
     */
    public function owedTo(string $consignor, string $currency): int;

    /**
     * @return list<StockLayer> the consignors' layers that still hold units — of one consignor, when given —, oldest
     *                          receipt first, at most `$limit`
     */
    public function consignedLayers(?string $consignor, int $limit): array;
}
