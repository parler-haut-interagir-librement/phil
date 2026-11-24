<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application\Contract;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostEntryKind;
use Phil\CommerceLogisticsBundle\Domain\Model\CostEntry;
use Phil\CommerceLogisticsBundle\Domain\ValueObject\SaleCost;
use Phil\CommerceLogisticsBundle\Domain\ValueObject\ValuedStock;
use Phil\Money\Money;

/**
 * What the shop's stock and its sales cost, read from the cost journal: what a report of margins, of the cost of goods
 * sold or of shrink, and a catalogue costing its composite products, read without writing anything. Units left on
 * consignment are never part of it. `Application\InventoryValue` implements it.
 *
 * A period runs from its start, included, to its end, excluded.
 */
interface InventoryValuation
{
    /**
     * What one unit of a SKU costs the shop now: the value of its units whose cost is known over their number, at every
     * location, rounded half up; with none on hand, the unit cost of its latest receipt at a known cost; null when it
     * was never received at a known cost.
     */
    public function unitCost(string $sku): ?Money;

    /**
     * The shop's stock at cost at a moment, per SKU and location.
     *
     * @return list<ValuedStock> by SKU, then location
     */
    public function valuedStock(DateTimeImmutable $at): array;

    /**
     * The cost of the goods sold over a period, per sale and SKU: its issues less its returns in the period.
     *
     * @return list<SaleCost> by reference, then SKU
     */
    public function costOfSales(DateTimeImmutable $from, DateTimeImmutable $until): array;

    /**
     * The cost journal of a period, of some kinds or all, in the order it was written, a page at a time: a shrink report
     * reads the `variance` entries, their reason and their value.
     *
     * @param list<CostEntryKind> $kinds none for every kind
     * @param string|null         $after the identifier of the last entry of the previous page
     * @param int                 $limit 1 to 1 000 entries per page
     *
     * @return list<CostEntry>
     */
    public function entries(DateTimeImmutable $from, DateTimeImmutable $until, array $kinds = [], ?string $after = null, int $limit = 500): array;
}
