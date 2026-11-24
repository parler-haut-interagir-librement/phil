<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Service;

use DateTimeImmutable;
use Phil\CommerceConsignmentBundle\Domain\Contract\LedgerRepository;
use Phil\CommerceConsignmentBundle\Domain\Contract\MarginCalculator;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Margin;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Movement;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\OwnerQuantity;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReturnSlip;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Sale;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\SaleLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\SlipLine;
use Phil\Money\Money;

/**
 * The stock of each SKU split by owner, in layers, and every movement of a layer written to the ledger.
 *
 * Each operation checks everything before it moves a unit: a shortage refuses the whole operation and leaves every layer
 * as it was. Nothing is committed here; the layers carry a version, so two requests that take from the same layer at
 * once cannot both succeed when the caller flushes.
 */
final readonly class Ledger
{
    public function __construct(
        private LedgerRepository $layers,
        private MarginCalculator $margins = new StandardMargins(),
    ) {
    }

    /**
     * Opens one layer per receipt line, each owned by the shop or by its consignor.
     *
     * @return list<StockLayer>
     */
    public function receive(Receipt $receipt, DateTimeImmutable $at, EntryKind $kind = EntryKind::Received): array
    {
        $opened = [];

        foreach ($receipt->lines as $line) {
            $layer = StockLayer::receive($line, $receipt->location, $receipt->reference, $at);
            $this->layers->addLayer($layer);
            $this->layers->record(LedgerEntry::movement($layer, $kind, $line->quantity, $receipt->reference, $at));
            $opened[] = $layer;
        }

        return $opened;
    }

    /**
     * The units of the SKU the ledger holds at the location, over every owner.
     */
    public function held(string $sku, string $location, ?Owner $owner = null): int
    {
        return array_sum(array_map(static fn (StockLayer $layer): int => $layer->remaining(), $this->layersAt($sku, $location, $owner)));
    }

    /**
     * The units of the SKU each owner holds, at one location or at all of them: the shop first, then the consignors in
     * the order of their identifiers. An owner who holds none is left out.
     *
     * @return list<OwnerQuantity>
     */
    public function split(string $sku, ?string $location = null): array
    {
        $quantities = [];

        foreach (null === $location ? $this->layers->layersOf($sku) : $this->layers->layersAt($sku, $location) as $layer) {
            $key = $layer->owner()->key();
            $quantities[$key] = new OwnerQuantity($layer->owner(), ($quantities[$key]->quantity ?? 0) + $layer->remaining());
        }

        // The repository gives only the layers that hold units. A numeric consignor is an integer key: the sort compares
        // the keys as strings, the shop's empty key first.
        ksort($quantities, \SORT_STRING);

        return array_values($quantities);
    }

    /**
     * Sells units of the SKU from the location, its layers taken in the issue order, and writes what each one brought in
     * and cost — or owes its consignor.
     */
    public function sell(string $reference, string $sku, string $location, int $quantity, Money $unitPrice, IssueOrder $order, DateTimeImmutable $at): Sale
    {
        InvalidValue::checkIdentifier($reference, 'reference');
        InvalidValue::checkQuantity($quantity);

        if ($unitPrice->isNegative()) {
            throw InvalidValue::because(\sprintf('A unit price is zero or more; %s is not.', $unitPrice->toDecimal()));
        }

        $lines = [];

        foreach ($this->plan($order->sort($this->layersAt($sku, $location)), $sku, $location, $quantity) as [$layer, $part]) {
            $layer->take($part);
            $margin = $this->margin($layer, $unitPrice, $part);
            $this->layers->record(LedgerEntry::sale($layer, EntryKind::Sold, -$part, $reference, $unitPrice, $margin, $at));
            $lines[] = new SaleLine($layer->id, $layer->location, $layer->owner(), $part, $margin);
        }

        return new Sale($reference, $sku, $lines);
    }

    /**
     * The units of the SKU a sale took off the shelves, at one location or at all of them; returns do not lower it.
     */
    public function sold(string $reference, string $sku, ?string $location = null): int
    {
        $sold = 0;

        foreach ($this->layers->entriesOf($reference, $sku) as $entry) {
            if (EntryKind::Sold === $entry->kind && (null === $location || $entry->location === $location)) {
                $sold -= $entry->quantity;
            }
        }

        return $sold;
    }

    /**
     * Takes back units of a sale into the layers they left, the units sold last first, at the location they are brought
     * back to: a layer split off from theirs when that location is another one. Their owner gets them back, and their
     * margin is the sale's, negated.
     */
    public function returnSold(string $reference, string $sku, string $location, int $quantity, DateTimeImmutable $at): Sale
    {
        InvalidValue::checkIdentifier($location, 'location');
        InvalidValue::checkQuantity($quantity);
        $returnable = $this->returnable($reference, $sku);
        $total = array_sum(array_map(static fn (array $part): int => $part[2], $returnable));

        if ($quantity > $total) {
            throw ConsignmentRefused::notSold($reference, $sku, $quantity, $total);
        }

        $lines = [];
        $left = $quantity;

        foreach ($returnable as [$layer, $unitPrice, $available]) {
            $part = min($left, $available);
            $left -= $part;
            $target = $this->restockAt($layer, $location, $part);
            $margin = $this->margin($layer, $unitPrice, $part)->negate();
            $this->layers->record(LedgerEntry::sale($target, EntryKind::Returned, $part, $reference, $unitPrice, $margin, $at));
            $lines[] = new SaleLine($target->id, $location, $layer->owner(), $part, $margin);

            if (0 === $left) {
                break;
            }
        }

        return new Sale($reference, $sku, $lines);
    }

    /**
     * Moves units of the SKU to another location, taken in the issue order — of one owner only, when given. They keep
     * their owner, their cost or terms and their receipt's instant.
     *
     * @return list<Movement> the units each target layer received
     */
    public function transfer(string $sku, string $from, string $to, int $quantity, IssueOrder $order, DateTimeImmutable $at, ?string $reference = null, ?Owner $owner = null): array
    {
        if ($from === $to) {
            throw ConsignmentRefused::sameLocation($from);
        }

        InvalidValue::checkIdentifier($to, 'location');
        InvalidValue::checkQuantity($quantity);
        $moved = [];

        foreach ($this->plan($order->sort($this->layersAt($sku, $from, $owner)), $sku, $from, $quantity) as [$layer, $part]) {
            $layer->take($part);
            $target = $this->restockAt($layer, $to, $part);
            $this->layers->record(LedgerEntry::movement($layer, EntryKind::TransferredOut, -$part, $reference, $at));
            $this->layers->record(LedgerEntry::movement($target, EntryKind::TransferredIn, $part, $reference, $at));
            $moved[] = new Movement($target->id, $to, $layer->owner(), $part);
        }

        return $moved;
    }

    /**
     * Sets what a count found of the SKU at the location — of one owner, when given — and returns the difference.
     *
     * Units missing leave the shop's own layers first, then the consigned ones, oldest first; a count of one owner
     * takes them from that owner's layers only. Units found go back to the owner's newest layer at the location, or
     * to a layer split from the owner's last receipt of the SKU; the shop when no owner is given.
     */
    public function count(string $sku, string $location, int $counted, DateTimeImmutable $at, ?string $reference = null, ?Owner $owner = null): int
    {
        if ($counted < 0) {
            throw InvalidValue::because(\sprintf('A count finds zero units or more; %d is not.', $counted));
        }

        $layers = $this->layersAt($sku, $location, $owner);
        $difference = $counted - array_sum(array_map(static fn (StockLayer $layer): int => $layer->remaining(), $layers));

        if ($difference < 0) {
            foreach ($this->plan(IssueOrder::OwnedFirst->sort($layers), $sku, $location, -$difference) as [$layer, $part]) {
                $layer->take($part);
                $this->layers->record(LedgerEntry::movement($layer, EntryKind::Counted, -$part, $reference, $at));
            }
        }

        if ($difference > 0) {
            $layer = $this->foundLayer($sku, $location, $owner ?? Owner::shop(), $layers, $difference);
            $this->layers->record(LedgerEntry::movement($layer, EntryKind::Counted, $difference, $reference, $at));
        }

        return $difference;
    }

    /**
     * Gives unsold units back to their consignor: each SKU's units taken from the consignor's layers at the location, the
     * oldest receipt first — the first due. A SKU with fewer units than asked refuses the whole slip before a unit moves.
     *
     * @param array<int|string, int> $quantities SKU → units, at most `Receipt::MAX_LINES` SKUs
     */
    public function giveBack(string $reference, Owner $consignor, string $location, array $quantities, DateTimeImmutable $at): ReturnSlip
    {
        InvalidValue::checkIdentifier($reference, 'reference');
        $this->checkConsigned($consignor, 'A return slip');

        if ([] === $quantities || \count($quantities) > Receipt::MAX_LINES) {
            throw InvalidValue::because(\sprintf('A return slip holds 1 to %d SKUs; %d were given.', Receipt::MAX_LINES, \count($quantities)));
        }

        $plans = [];

        foreach ($quantities as $sku => $quantity) {
            InvalidValue::checkQuantity($quantity);
            $plans[] = $this->plan(IssueOrder::OldestFirst->sort($this->layersAt((string) $sku, $location, $consignor)), (string) $sku, $location, $quantity);
        }

        $lines = [];

        foreach (array_merge(...$plans) as [$layer, $part]) {
            $layer->take($part);
            $this->layers->record(LedgerEntry::movement($layer, EntryKind::GivenBack, -$part, $reference, $at));
            $lines[] = new SlipLine($layer->id, $layer->sku, $part);
        }

        return new ReturnSlip($reference, $consignor, $location, $lines);
    }

    /**
     * The shop buys unsold units of a consignor at the location, the oldest receipt first: they leave the consignor's
     * layers and reach layers of the shop's own at the unit cost, where they stand, without moving. The consignor is
     * owed the cost, as for a sale.
     *
     * @param Money|null $unitCost the agreed cost of one unit; null for the consignor's share, on layers with a share
     */
    public function buyOut(string $reference, Owner $consignor, string $sku, string $location, int $quantity, ?Money $unitCost, DateTimeImmutable $at): ReturnSlip
    {
        InvalidValue::checkIdentifier($reference, 'reference');
        InvalidValue::checkQuantity($quantity);
        $this->checkConsigned($consignor, 'A buy-out');

        if ($unitCost?->isNegative() ?? false) {
            throw InvalidValue::because(\sprintf('A unit cost is zero or more; %s is not.', $unitCost->toDecimal()));
        }

        $parts = [];

        foreach ($this->plan(IssueOrder::OldestFirst->sort($this->layersAt($sku, $location, $consignor)), $sku, $location, $quantity) as [$layer, $part]) {
            $parts[] = [$layer, $part, $unitCost ?? $layer->terms()->share ?? throw ConsignmentRefused::noBuyOutCost($sku, $consignor->describe())];
        }

        $lines = [];

        foreach ($parts as [$layer, $part, $cost]) {
            $layer->take($part);
            $owed = $cost->multiply($part);
            $shop = StockLayer::boughtFrom($layer, $part, $cost, $reference, $at);
            $this->layers->addLayer($shop);
            $this->layers->record(LedgerEntry::sale($layer, EntryKind::BoughtOut, -$part, $reference, $cost, Margin::of($owed, $owed), $at));
            $this->layers->record(LedgerEntry::movement($shop, EntryKind::BoughtIn, $part, $reference, $at));
            $lines[] = new SlipLine($layer->id, $sku, $part, $shop->id, $owed);
        }

        return new ReturnSlip($reference, $consignor, $location, $lines);
    }

    /**
     * The layers of the SKU at the location that still hold units — the repository gives no other —, of one owner when
     * given.
     *
     * @return list<StockLayer>
     */
    private function layersAt(string $sku, string $location, ?Owner $owner = null): array
    {
        $layers = [];

        foreach ($this->layers->layersAt($sku, $location) as $layer) {
            if (!$owner instanceof Owner || $layer->owner()->equals($owner)) {
                $layers[] = $layer;
            }
        }

        return $layers;
    }

    /**
     * The units to take from each layer, in their order, refusing before anything moves when they hold too few.
     *
     * @param list<StockLayer> $layers
     *
     * @return list<array{StockLayer, int}>
     */
    private function plan(array $layers, string $sku, string $location, int $quantity): array
    {
        $held = array_sum(array_map(static fn (StockLayer $layer): int => $layer->remaining(), $layers));

        if ($quantity > $held) {
            throw ConsignmentRefused::shortage($sku, $location, $quantity, $held);
        }

        $plan = [];
        $left = $quantity;

        foreach ($layers as $layer) {
            $part = min($left, $layer->remaining());
            $plan[] = [$layer, $part];
            $left -= $part;

            if (0 === $left) {
                break;
            }
        }

        return $plan;
    }

    /**
     * What each layer of a sale may still take back, at each price it sold at, the units sold last first.
     *
     * @return list<array{StockLayer, Money, int}>
     */
    private function returnable(string $reference, string $sku): array
    {
        $parts = [];

        foreach ($this->layers->entriesOf($reference, $sku) as $entry) {
            $unitPrice = $entry->unitPrice();

            if (!$unitPrice instanceof Money) {
                continue;
            }

            $layer = $this->layers->layer($entry->layer);

            if (!$layer instanceof StockLayer) {
                continue;
            }

            // A return goes to the layer's origin family: units brought back elsewhere sit in a layer split from the one
            // they were sold from, and still count against it.
            $key = $layer->origin.' '.$unitPrice->currency->code.' '.$unitPrice->minorUnits;

            if (EntryKind::Sold === $entry->kind) {
                $parts[$key] ??= [$layer, $unitPrice, 0];
                $parts[$key][2] -= $entry->quantity;
            }

            if (EntryKind::Returned === $entry->kind && isset($parts[$key])) {
                $parts[$key][2] -= $entry->quantity;
            }
        }

        return array_values(array_reverse(array_filter($parts, static fn (array $part): bool => $part[2] > 0)));
    }

    /**
     * Puts units of a layer back at a location: on the layer itself when it is there, on a layer split from the same
     * receipt that is there, or on a new one split from it. Every layer of a receipt has the receipt's owner.
     */
    private function restockAt(StockLayer $layer, string $location, int $quantity): StockLayer
    {
        foreach ($this->layers->layersFrom($layer->origin) as $candidate) {
            if ($candidate->location === $location) {
                $candidate->restock($quantity);

                return $candidate;
            }
        }

        $copy = $layer->copyAt($location, $quantity);
        $this->layers->addLayer($copy);

        return $copy;
    }

    /**
     * @param list<StockLayer> $layers the owner's layers at the location
     */
    private function foundLayer(string $sku, string $location, Owner $owner, array $layers, int $found): StockLayer
    {
        $own = array_filter($layers, static fn (StockLayer $layer): bool => $layer->owner()->equals($owner));

        if ([] !== $own) {
            usort($own, IssueOrder::age(...));
            $newest = $own[\count($own) - 1];
            $newest->restock($found);

            return $newest;
        }

        $latest = $this->layers->latestLayer($sku, $owner->key()) ?? throw ConsignmentRefused::noLayerFor($sku, $owner->describe());

        return $this->restockAt($latest, $location, $found);
    }

    private function checkConsigned(Owner $owner, string $what): void
    {
        if (!$owner->isConsigned()) {
            throw ConsignmentRefused::notConsigned($what);
        }
    }

    private function margin(StockLayer $layer, Money $unitPrice, int $quantity): Margin
    {
        $terms = $layer->terms();

        return $terms instanceof ConsignmentTerms
            ? $this->margins->consigned($unitPrice, $quantity, $terms)
            : $this->margins->owned($unitPrice, $quantity, $layer->unitCost() ?? Money::zero($unitPrice->currency));
    }
}
