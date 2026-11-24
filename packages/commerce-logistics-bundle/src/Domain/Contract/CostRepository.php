<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostEntryKind;
use Phil\CommerceLogisticsBundle\Domain\Model\CostEntry;
use Phil\CommerceLogisticsBundle\Domain\Model\CostLayer;

/**
 * Finds and stores cost layers and the cost journal. `Infrastructure\Doctrine\DoctrineLogisticsRepository` is the
 * Doctrine adapter; what it was given and not flushed yet counts as stored.
 */
interface CostRepository
{
    /**
     * @return list<CostLayer> the layers of a SKU at a location, of the shop and of owners, the oldest received first
     */
    public function layersOf(string $sku, string $location): array;

    /**
     * @return list<CostLayer> the shop's layers of a SKU, at every location
     */
    public function shopLayersOf(string $sku): array;

    public function addLayer(CostLayer $layer): void;

    public function removeLayer(CostLayer $layer): void;

    public function addEntry(CostEntry $entry): void;

    /**
     * @return list<CostEntry> the issues and returns of a SKU under a reference — an order's number —, at every
     *                         location, oldest first
     */
    public function salesEntriesOf(string $reference, string $sku): array;

    /**
     * The shop's latest receipt of a SKU with a known cost, at any location.
     */
    public function latestCostedReceipt(string $sku): ?CostEntry;

    /**
     * The shop's units whose cost is known and their value, per SKU, location and currency, from its entries up to a
     * moment; a SKU whose entries add up to nothing at a location is left out.
     *
     * @return list<array{sku: string, location: string, currency: string, units: int, value: int}> by SKU, then
     *                                                                                              location
     */
    public function valuation(DateTimeImmutable $at): array;

    /**
     * The shop's issues and returns of each sale over a period, summed per reference, SKU and currency as the journal
     * holds them: negative for what left and did not come back.
     *
     * @return list<array{reference: string, sku: string, currency: string, units: int, uncosted: int, value: int}> by
     *                                                                                                              reference,
     *                                                                                                              then SKU
     */
    public function salesCost(DateTimeImmutable $from, DateTimeImmutable $until): array;

    /**
     * The entries of a period, of some kinds or all, oldest first, one page at a time.
     *
     * @param list<CostEntryKind> $kinds none for every kind
     * @param string|null         $after the identifier of the last entry of the previous page
     * @param int<1, max>         $limit
     *
     * @return list<CostEntry>
     */
    public function entries(DateTimeImmutable $from, DateTimeImmutable $until, array $kinds, ?string $after, int $limit): array;
}
