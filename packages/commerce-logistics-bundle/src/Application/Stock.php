<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Model\StockPolicy;
use Phil\CommerceLogisticsBundle\Domain\Repository\LocationRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockPolicyRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\Domain\Stock\ReservationState;

/**
 * Stock in and out, each movement written to the journal, and the reservations of placed orders.
 *
 * **A SKU is tracked once it has a stock level somewhere**: a product never received — a download, a service — is
 * never reserved. A SKU whose `StockPolicy` allows backorders sells without stock: its shortfall is reserved at its
 * first open location and leaves once goods are received. With `ConventionStock`, the convention items of an order
 * placed online while a convention is near go to print instead. Nothing is committed here.
 */
final readonly class Stock
{
    public function __construct(
        private LocationRepository $locations,
        private StockRepository $stock,
        private ?StockPolicyRepository $policies = null,
        private ?ConventionStock $conventions = null,
    ) {
    }

    /**
     * Sells the SKU without stock from now on: a shortage no longer refuses an order, with neither threshold nor ceiling.
     */
    public function allowBackorders(string $sku): void
    {
        $this->policyOf($sku)->allowBackorders();
    }

    public function forbidBackorders(string $sku): void
    {
        $this->policyOf($sku)->forbidBackorders();
    }

    public function allowsBackorders(string $sku): bool
    {
        return true === $this->policies?->policy($sku)?->allowsBackorders();
    }

    /**
     * The units of a SKU reserved beyond what is on hand, over every location: what its backorders still wait for.
     */
    public function backordered(string $sku): int
    {
        return array_sum(array_map(static fn (StockLevel $level): int => $level->backordered(), $this->stock->levelsOf($sku)));
    }

    public function receive(string $sku, string $location, int $quantity, DateTimeImmutable $at, ?string $reference = null): void
    {
        $level = $this->levelAt($sku, $location);
        $level->receive($quantity);

        $this->stock->record(StockMovement::of($level, MovementKind::Received, $quantity, 0, $reference, $at));
    }

    /**
     * Sets what a count found on hand; the journal keeps the difference.
     */
    public function count(string $sku, string $location, int $counted, DateTimeImmutable $at, ?string $reference = null): void
    {
        $level = $this->levelAt($sku, $location);
        $this->stock->record(StockMovement::of($level, MovementKind::Counted, $level->count($counted), 0, $reference, $at));
    }

    public function transfer(string $sku, string $from, string $to, int $quantity, DateTimeImmutable $at, ?string $reference = null): void
    {
        if ($from === $to) {
            throw StockRefused::sameLocation($from);
        }

        $source = $this->levelAt($sku, $from);
        $target = $this->levelAt($sku, $to);
        $source->remove($quantity);
        $target->receive($quantity);
        $this->stock->record(StockMovement::of($source, MovementKind::TransferredOut, -$quantity, 0, $reference, $at));
        $this->stock->record(StockMovement::of($target, MovementKind::TransferredIn, $quantity, 0, $reference, $at));
    }

    /**
     * What may still be reserved of a SKU, over the open locations.
     */
    public function available(string $sku): int
    {
        $open = array_map(static fn (Location $location): string => $location->code, $this->locations->openLocations());
        $available = 0;

        foreach ($this->stock->levelsOf($sku) as $level) {
            if (\in_array($level->location, $open, true)) {
                $available += $level->available();
            }
        }

        return $available;
    }

    /**
     * Sets aside the tracked lines of a placed order, from the open locations by priority. A shortage on any line
     * reserves nothing, unless its SKU allows backorders. An order reserved once is not reserved again: a second call —
     * a listener run twice, a retry after a failed flush — changes nothing, and neither does a call after its
     * convention items went to print.
     */
    public function reserve(Order $order, DateTimeImmutable $at): void
    {
        if (OrderState::Placed !== $order->state()) {
            throw StockRefused::notReservable($order->number);
        }

        if ([] !== $this->stock->reservationsOf($order->number) || true === $this->conventions?->routed($order->number)) {
            return;
        }

        $wanted = $this->wanted($order);

        if ([] === $wanted) {
            return;
        }

        $printed = $this->conventions?->toPrint($order, $wanted, $at) ?? [];
        $plan = $this->plan($order, array_diff_key($wanted, $printed));

        foreach ($plan as [$level, $quantity, $backorder]) {
            $this->stock->addReservation($backorder ? Reservation::backorder($order->number, $level, $quantity) : Reservation::hold($order->number, $level, $quantity));
            $this->stock->record(StockMovement::of($level, $backorder ? MovementKind::Backordered : MovementKind::Reserved, 0, $quantity, $order->number, $at));
        }

        $this->conventions?->print($order->number, $printed, $at);
    }

    /**
     * Gives back what is still held for an order: it was cancelled.
     */
    public function release(string $orderNumber, DateTimeImmutable $at): void
    {
        foreach ($this->held($orderNumber) as [$reservation, $level]) {
            $released = $reservation->remaining();
            $reservation->release($level);
            $this->stock->record(StockMovement::of($level, MovementKind::Released, 0, -$released, $orderNumber, $at));
        }
    }

    /**
     * Takes what is held for an order off the shelf: it left.
     */
    public function ship(string $orderNumber, DateTimeImmutable $at): void
    {
        $held = $this->held($orderNumber);
        $this->checkOnHand(array_map(static fn (array $pair): array => [$pair[1], $pair[0]->remaining()], $held));

        foreach ($held as [$reservation, $level]) {
            $shipped = $reservation->remaining();
            $reservation->ship($level);
            $this->stock->record(StockMovement::of($level, MovementKind::Shipped, -$shipped, -$shipped, $orderNumber, $at));
        }
    }

    /**
     * Takes part of what is held for an order off the shelf: a partial shipment of one SKU, from its reservations
     * in the order they were made.
     *
     * @return int the units taken from reservations; zero for a SKU the order holds none of — one not tracked
     */
    public function shipPart(string $orderNumber, string $sku, int $quantity, DateTimeImmutable $at): int
    {
        $held = [];

        foreach ($this->held($orderNumber) as [$reservation, $level]) {
            if ($reservation->sku === $sku) {
                $held[] = [$reservation, $level];
            }
        }

        if ([] === $held) {
            return 0;
        }

        $remaining = array_sum(array_map(static fn (array $pair): int => $pair[0]->remaining(), $held));

        if ($quantity > $remaining) {
            throw StockRefused::shortage($orderNumber, $sku, $quantity - $remaining);
        }

        $parts = [];
        $left = $quantity;

        foreach ($held as [$reservation, $level]) {
            $part = min($left, $reservation->remaining());

            if ($part > 0) {
                $parts[] = [$reservation, $level, $part];
                $left -= $part;
            }
        }

        $this->checkOnHand(array_map(static fn (array $part): array => [$part[1], $part[2]], $parts));

        foreach ($parts as [$reservation, $level, $part]) {
            $reservation->shipPart($level, $part);
            $this->stock->record(StockMovement::of($level, MovementKind::Shipped, -$part, -$part, $orderNumber, $at));
        }

        return $quantity;
    }

    /**
     * @param array<int|string, int> $wanted SKU → units to take from the shelves; a numeric SKU is an integer key
     *
     * @return list<array{StockLevel, int, bool}> the level, the units and whether they are a backorder
     */
    private function plan(Order $order, array $wanted): array
    {
        $skus = array_map(strval(...), array_keys($wanted));

        if ([] === $skus) {
            return [];
        }

        $levels = $this->openLevels($skus);
        $backorders = $this->backorderable($skus);
        $plan = [];

        foreach ($wanted as $sku => $missing) {
            $sku = (string) $sku;

            if (!isset($levels[$sku])) {
                continue;
            }

            foreach ($levels[$sku] as $level) {
                $taken = min($missing, $level->available());

                if ($taken > 0) {
                    $plan[] = [$level, $taken, false];
                    $missing -= $taken;
                }
            }

            if ($missing > 0) {
                $plan[] = \in_array($sku, $backorders, true) ? [$levels[$sku][0], $missing, true] : throw StockRefused::shortage($order->number, $sku, $missing);
            }
        }

        return $plan;
    }

    /**
     * Refuses a shipment before anything moves when a level does not hold on hand every unit asked of it: a backorder
     * not received yet.
     *
     * @param list<array{StockLevel, int}> $parts
     */
    private function checkOnHand(array $parts): void
    {
        $asked = [];

        foreach ($parts as [$level, $quantity]) {
            $key = $level->sku."\0".$level->location;
            $asked[$key] = [$level, ($asked[$key][1] ?? 0) + $quantity];
        }

        foreach ($asked as [$level, $quantity]) {
            if ($quantity > $level->onHand()) {
                throw StockRefused::notOnHand($level->sku, $level->location, $quantity, $level->onHand());
            }
        }
    }

    /**
     * @return list<array{Reservation, StockLevel}>
     */
    private function held(string $orderNumber): array
    {
        $held = [];

        foreach ($this->stock->reservationsOf($orderNumber) as $reservation) {
            $level = $this->stock->level($reservation->sku, $reservation->location);

            if (ReservationState::Held === $reservation->state() && $level instanceof StockLevel) {
                $held[] = [$reservation, $level];
            }
        }

        return $held;
    }

    /**
     * The levels of the SKUs at the open locations, in one lookup of the locations and one of the levels.
     *
     * @param list<string> $skus
     *
     * @return array<string, non-empty-list<StockLevel>> SKU → its levels at open locations, by the locations' priority
     */
    private function openLevels(array $skus): array
    {
        $rank = [];

        foreach ($this->locations->openLocations() as $index => $location) {
            $rank[$location->code] = $index;
        }

        $levels = array_filter(
            $this->stock->levelsOfSkus($skus),
            static fn (StockLevel $level): bool => isset($rank[$level->location]),
        );
        usort($levels, static fn (StockLevel $left, StockLevel $right): int => $rank[$left->location] <=> $rank[$right->location]);

        $bySku = [];

        foreach ($levels as $level) {
            $bySku[$level->sku][] = $level;
        }

        return $bySku;
    }

    /**
     * The units of each SKU the order's product lines hold: two configured lines of one product share its SKU, and
     * their units are reserved together.
     *
     * @return array<int|string, int> SKU → units; a numeric SKU is an integer key, as PHP makes it
     */
    private function wanted(Order $order): array
    {
        $wanted = [];

        foreach ($order->lines() as $line) {
            if ('product' === $line->reference->type) {
                $wanted[$line->reference->id] = ($wanted[$line->reference->id] ?? 0) + $line->quantity;
            }
        }

        return $wanted;
    }

    /**
     * @param list<string> $skus
     *
     * @return list<string> the SKUs that sell without stock
     */
    private function backorderable(array $skus): array
    {
        $allowed = [];

        foreach ($this->policies?->policiesOf($skus) ?? [] as $policy) {
            if ($policy->allowsBackorders()) {
                $allowed[] = $policy->sku;
            }
        }

        return $allowed;
    }

    private function policyOf(string $sku): StockPolicy
    {
        $policies = $this->policies ?? throw StockRefused::noPolicies();
        $policy = $policies->policy($sku);

        if (!$policy instanceof StockPolicy) {
            $policy = StockPolicy::of($sku);
            $policies->addPolicy($policy);
        }

        return $policy;
    }

    private function levelAt(string $sku, string $location): StockLevel
    {
        $level = $this->stock->level($sku, $location);

        if ($level instanceof StockLevel) {
            return $level;
        }

        $level = StockLevel::of($sku, $this->locations->findLocation($location) ?? throw StockRefused::unknownLocation($location));
        $this->stock->addLevel($level);

        return $level;
    }
}
