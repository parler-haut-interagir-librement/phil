<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Application\Contract\StockOwnership;
use Phil\CommerceLogisticsBundle\Domain\Contract\CostRepository;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostEntryKind;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostingMethod;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostShare;
use Phil\CommerceLogisticsBundle\Domain\Cost\VarianceReason;
use Phil\CommerceLogisticsBundle\Domain\Exception\CostRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\CostEntry;
use Phil\CommerceLogisticsBundle\Domain\Model\CostLayer;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * The value of the stock as it moves: `Stock` and `Purchasing` call it, when costing is enabled, with each movement
 * they write to the stock journal, and it keeps the cost layers and the cost journal in step. An application reads
 * values through `Contract\InventoryValuation`, never by calling this service, which would count a movement twice.
 *
 * **Whose units they are comes first.** Units received with an owner — left on consignment — go to a layer of that
 * owner, which holds units and no value: they never enter the shop's layers nor its stock value, whatever their
 * purchase order says they cost. When units leave a level that holds both, `Contract\StockOwnership` says whose leave.
 *
 * **The shop's units are valued by the configured method**, per SKU and location: a weighted average keeps one pool,
 * first in, first out one layer per receipt. Units the shop received without a unit cost, or before costing began, are
 * uncosted: they move no value, and the journal counts them apart. Nothing is committed here.
 */
final readonly class Costing
{
    /** Keys an owner in an array, so that an owner made of digits stays a string. */
    private const string KEY = '@';

    public function __construct(
        private CostRepository $costs,
        private CostingMethod $method,
        private Currency $currency,
        private StockOwnership $owners = new ShopUnitsFirst(),
    ) {
    }

    public function method(): CostingMethod
    {
        return $this->method;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    /**
     * Units came in: an owner's units to a layer of theirs, the shop's units at their unit cost, or uncosted without
     * one.
     */
    public function receive(string $sku, string $location, int $units, ?Money $unitCost, ?string $owner, ?string $reference, DateTimeImmutable $at): void
    {
        if (null !== $owner) {
            $this->costs->addLayer(CostLayer::owned($sku, $location, $owner, $units, $this->currency, $at, $reference));
            $this->costs->addEntry(CostEntry::owned($sku, $location, CostEntryKind::Received, $owner, $units, $this->currency, $reference, $at));

            return;
        }

        if (!$unitCost instanceof Money) {
            $this->costs->addEntry(CostEntry::shop($sku, $location, CostEntryKind::Received, $units, $units, Money::zero($this->currency), $reference, $at));

            return;
        }

        $value = $this->checked($sku, $unitCost)->multiply($units);
        $this->addShopUnits($sku, $location, $units, $value, $reference, $at, $at);
        $this->costs->addEntry(CostEntry::shop($sku, $location, CostEntryKind::Received, $units, 0, $value, $reference, $at));
    }

    /**
     * Adds a share of a landed cost to the units a receipt brought in under a reference: to the pool of a weighted
     * average, or to the layer of that receipt under first in, first out.
     */
    public function land(string $sku, string $location, Money $share, string $reference, DateTimeImmutable $at): void
    {
        $this->checked($sku, $share);
        $layer = null;

        if (CostingMethod::WeightedAverage === $this->method) {
            $layer = $this->pool($this->shopLayers($sku, $location));
        } else {
            foreach ($this->shopLayers($sku, $location) as $candidate) {
                if ($reference === $candidate->reference) {
                    $layer = $candidate;
                }
            }
        }

        if (!$layer instanceof CostLayer) {
            throw CostRefused::noLayer($sku, $location, $reference);
        }

        $layer->addValue($share);
        $this->costs->addEntry(CostEntry::shop($sku, $location, CostEntryKind::Landed, 0, 0, $share, $reference, $at));
    }

    /**
     * Units left with an order: the cost of the sale.
     *
     * @param int $onHandBefore the units the level held before they left
     */
    public function issue(string $sku, string $location, int $units, int $onHandBefore, string $orderNumber, DateTimeImmutable $at): void
    {
        $this->journalOut($sku, $location, CostEntryKind::Issued, $this->leave($sku, $location, $units, $onHandBefore, $orderNumber), $orderNumber, $at, null);
    }

    /**
     * Units a customer sent back from an order come in at the cost they left at — never at today's cost —, back to
     * whoever they left from: the shop's units first, as far as the order's issues and earlier returns tell, then each
     * owner's. Units beyond what the cost journal saw leave — a sale made before costing began — come back uncosted.
     */
    public function returnFromSale(string $orderNumber, string $sku, string $location, int $units, DateTimeImmutable $at): void
    {
        // Out of the shop's units: those still out, those of them whose cost was known, and their value. Issues are
        // negative in the journal and returns positive, so each entry takes its units off what is still out.
        [$outstanding, $costedOutstanding, $outstandingValue] = [0, 0, 0];
        $owners = [];

        foreach ($this->costs->salesEntriesOf($orderNumber, $sku) as $entry) {
            if (null !== $entry->owner) {
                $owners[self::KEY.$entry->owner] = [$entry->owner, ($owners[self::KEY.$entry->owner][1] ?? 0) - $entry->units];
            } else {
                $outstanding -= $entry->units;
                $costedOutstanding -= $entry->costedUnits();
                $outstandingValue -= $entry->value()->minorUnits;
            }
        }

        $left = $units;
        $part = min($left, $outstanding);
        $costed = min($part, $costedOutstanding);
        $value = Money::zero($this->currency);

        if ($costed > 0) {
            $value = Money::of($outstandingValue, $this->currency)->allocate($costed, $costedOutstanding - $costed)[0];
            $this->addShopUnits($sku, $location, $costed, $value, $orderNumber, $at, $at);
        }

        $left -= $part;

        foreach ($owners as [$owner, $returnable]) {
            $back = min($left, $returnable);

            if ($back > 0) {
                $this->costs->addLayer(CostLayer::owned($sku, $location, $owner, $back, $this->currency, $at, $orderNumber));
                $this->costs->addEntry(CostEntry::owned($sku, $location, CostEntryKind::Returned, $owner, $back, $this->currency, $orderNumber, $at));
                $left -= $back;
            }
        }

        $shopUnits = $part + $left;

        if ($shopUnits > 0) {
            $this->costs->addEntry(CostEntry::shop($sku, $location, CostEntryKind::Returned, $shopUnits, $shopUnits - $costed, $value, $orderNumber, $at));
        }
    }

    /**
     * A count or an adjustment found units missing or extra. Missing units leave as a shipment does, at their cost;
     * extra units are the shop's, at the level's current unit cost — the pool's average, or the newest layer's —, and
     * uncosted when the level holds no costed unit.
     *
     * @param int $onHandBefore the units the level held before the difference
     */
    public function vary(string $sku, string $location, int $difference, int $onHandBefore, ?VarianceReason $reason, ?string $reference, DateTimeImmutable $at): void
    {
        if ($difference < 0) {
            $this->journalOut($sku, $location, CostEntryKind::Variance, $this->leave($sku, $location, -$difference, $onHandBefore, $reference), $reference, $at, $reason);

            return;
        }

        if (0 === $difference) {
            return;
        }

        $layers = $this->shopLayers($sku, $location);
        $basis = CostingMethod::WeightedAverage === $this->method ? $this->pool($layers) : ($layers[\count($layers) - 1] ?? null);

        if (!$basis instanceof CostLayer) {
            $this->costs->addEntry(CostEntry::shop($sku, $location, CostEntryKind::Variance, $difference, $difference, Money::zero($this->currency), $reference, $at, $reason));

            return;
        }

        $value = CostShare::of($basis->value(), $difference, $basis->units());
        $this->addShopUnits($sku, $location, $difference, $value, $reference, $at, $at);
        $this->costs->addEntry(CostEntry::shop($sku, $location, CostEntryKind::Variance, $difference, 0, $value, $reference, $at, $reason));
    }

    /**
     * Units moved from one location to another take their cost and their owner along: under first in, first out, each
     * layer keeps the moment it was received, so the units keep their place in line.
     *
     * @param int $onHandBefore the units the source level held before they left
     */
    public function transfer(string $sku, string $from, string $to, int $units, int $onHandBefore, ?string $reference, DateTimeImmutable $at): void
    {
        $left = $this->leave($sku, $from, $units, $onHandBefore, $reference);
        $this->journalOut($sku, $from, CostEntryKind::TransferredOut, $left, $reference, $at, null);

        $costed = 0;
        $value = Money::zero($this->currency);

        foreach ($left['shop'] as [$layer, $part, $taken]) {
            $this->addShopUnits($sku, $to, $part, $taken, $layer->reference, $layer->receivedAt, $at);
            $costed += $part;
            $value = $value->add($taken);
        }

        $moved = $costed + $left['uncosted'];

        if ($moved > 0) {
            $this->costs->addEntry(CostEntry::shop($sku, $to, CostEntryKind::TransferredIn, $moved, $left['uncosted'], $value, $reference, $at));
        }

        foreach ($left['owners'] as [$owner, $pieces]) {
            $part = 0;

            foreach ($pieces as [$layer, $piece]) {
                $this->costs->addLayer(CostLayer::owned($sku, $to, $owner, $piece, $this->currency, $layer->receivedAt, $layer->reference));
                $part += $piece;
            }

            $this->costs->addEntry(CostEntry::owned($sku, $to, CostEntryKind::TransferredIn, $owner, $part, $this->currency, $reference, $at));
        }
    }

    /**
     * Takes units out of a level's layers, each owner's as `StockOwnership` says and the shop's from its own layers,
     * the oldest first; the shop's units beyond its layers are uncosted.
     *
     * @return array{shop: list<array{CostLayer, int, Money}>, uncosted: int, owners: list<array{string, list<array{CostLayer, int, Money}>}>}
     */
    private function leave(string $sku, string $location, int $units, int $onHandBefore, ?string $reference): array
    {
        $shopLayers = [];
        $ownerLayers = [];
        $consigned = [];

        foreach ($this->costs->layersOf($sku, $location) as $layer) {
            if (null === $layer->owner) {
                $shopLayers[] = $layer;

                continue;
            }

            $key = self::KEY.$layer->owner;
            $ownerLayers[$key][] = $layer;
            $consigned[$key] = [$layer->owner, ($consigned[$key][1] ?? 0) + $layer->units()];
        }

        $held = array_map(static fn (array $owned): int => $owned[1], $consigned);
        $shopUnits = max(0, $onHandBefore - array_sum($held));
        $shares = $this->owners->leaving($sku, $location, $units, $shopUnits, array_values($consigned), $reference);
        $shared = 0;

        foreach ($shares as [$owner, $part]) {
            $holds = $held[self::KEY.$owner] ?? 0;

            if ($part < 0 || $part > $holds) {
                throw CostRefused::share($sku, $location, $units);
            }

            $held[self::KEY.$owner] = $holds - $part;
            $shared += $part;
        }

        $shopPart = $units - $shared;

        if ($shopPart < 0 || $shopPart > $shopUnits) {
            throw CostRefused::share($sku, $location, $units);
        }

        $layers = CostingMethod::WeightedAverage === $this->method ? array_filter([$this->pool($shopLayers)]) : $shopLayers;
        $shop = $this->take($layers, $shopPart);
        $owners = [];

        foreach ($shares as [$owner, $part]) {
            if ($part > 0) {
                $owners[] = [$owner, $this->take($ownerLayers[self::KEY.$owner], $part)];
            }
        }

        return ['shop' => $shop, 'uncosted' => $shopPart - array_sum(array_column($shop, 1)), 'owners' => $owners];
    }

    /**
     * @param array{shop: list<array{CostLayer, int, Money}>, uncosted: int, owners: list<array{string, list<array{CostLayer, int, Money}>}>} $left
     */
    private function journalOut(string $sku, string $location, CostEntryKind $kind, array $left, ?string $reference, DateTimeImmutable $at, ?VarianceReason $reason): void
    {
        $costed = array_sum(array_column($left['shop'], 1));
        $units = $costed + $left['uncosted'];

        if ($units > 0) {
            $value = array_reduce($left['shop'], static fn (Money $sum, array $piece): Money => $sum->add($piece[2]), Money::zero($this->currency));
            $this->costs->addEntry(CostEntry::shop($sku, $location, $kind, -$units, -$left['uncosted'], $value->negate(), $reference, $at, $reason));
        }

        foreach ($left['owners'] as [$owner, $pieces]) {
            $this->costs->addEntry(CostEntry::owned($sku, $location, $kind, $owner, -array_sum(array_column($pieces, 1)), $this->currency, $reference, $at, $reason));
        }
    }

    /**
     * Takes units from layers, the oldest first, removing each layer it empties.
     *
     * @param array<int, CostLayer> $layers
     *
     * @return list<array{CostLayer, int, Money}> each layer taken from, the units and the value they left with
     */
    private function take(array $layers, int $units): array
    {
        $pieces = [];
        $left = $units;

        foreach ($layers as $layer) {
            if ($left < 1) {
                break;
            }

            $part = min($left, $layer->units());

            if ($part < 1) {
                continue;
            }

            $pieces[] = [$layer, $part, $layer->take($part)];
            $left -= $part;

            if (0 === $layer->units()) {
                $this->costs->removeLayer($layer);
            }
        }

        return $pieces;
    }

    /**
     * The shop's units join a level: into its pool under a weighted average, or as a layer of their own.
     */
    private function addShopUnits(string $sku, string $location, int $units, Money $value, ?string $reference, DateTimeImmutable $receivedAt, DateTimeImmutable $at): void
    {
        $pool = CostingMethod::WeightedAverage === $this->method ? $this->pool($this->shopLayers($sku, $location)) : null;

        if ($pool instanceof CostLayer) {
            $pool->add($units, $value);

            return;
        }

        $this->costs->addLayer(CostLayer::shop($sku, $location, $units, $value, CostingMethod::WeightedAverage === $this->method ? $at : $receivedAt, $reference));
    }

    /**
     * @return list<CostLayer> the shop's layers of a SKU at a location, the oldest first
     */
    private function shopLayers(string $sku, string $location): array
    {
        return array_values(array_filter($this->costs->layersOf($sku, $location), static fn (CostLayer $layer): bool => $layer->isShops()));
    }

    /**
     * The pool of a weighted average: the shop's layers merged into the oldest one — several are left by first in,
     * first out when the method changed.
     *
     * @param list<CostLayer> $layers
     */
    private function pool(array $layers): ?CostLayer
    {
        $pool = array_shift($layers);

        foreach ($layers as $layer) {
            \assert($pool instanceof CostLayer);
            $pool->add($layer->units(), $layer->value());
            $this->costs->removeLayer($layer);
        }

        return $pool;
    }

    private function checked(string $sku, Money $cost): Money
    {
        if ($cost->isNegative()) {
            throw CostRefused::negative($sku, $cost);
        }

        if (!$cost->currency->equals($this->currency)) {
            throw CostRefused::currency($sku, $this->currency->code, $cost->currency->code);
        }

        return $cost;
    }
}
