<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostShare;
use Phil\CommerceLogisticsBundle\Domain\Exception\CostRefused;
use Phil\CommerceLogisticsBundle\Domain\Exception\PurchaseRefused;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\PurchaseOrder;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseLine;
use Phil\CommerceLogisticsBundle\Domain\Repository\LocationRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\PurchaseOrderRepository;
use Phil\Money\Money;

/**
 * Goods ordered from suppliers and received against their purchase orders: each receipt adds the units to stock at the
 * order's location, in the journal under the order's number. With `Costing`, the units come in at their line's unit
 * cost and owner, and a landed cost is shared out over the lines that bear it. Nothing is committed here.
 */
final readonly class Purchasing
{
    public function __construct(
        private PurchaseOrderRepository $orders,
        private LocationRepository $locations,
        private Stock $stock,
        private ?Costing $costing = null,
    ) {
    }

    /**
     * @param array<int|string, int|PurchaseLine> $lines SKU → units ordered, or a `PurchaseLine` that also gives the
     *                                                   unit cost and the owner; a numeric SKU is an integer key
     */
    public function order(string $number, string $supplier, string $location, array $lines, DateTimeImmutable $at, ?DateTimeImmutable $expectedOn = null): PurchaseOrder
    {
        if ($this->orders->findPurchaseOrder($number) instanceof PurchaseOrder) {
            throw PurchaseRefused::duplicate($number);
        }

        $order = PurchaseOrder::of($number, $supplier, $this->locations->findLocation($location) ?? throw StockRefused::unknownLocation($location), $lines, $at, $expectedOn);
        $this->orders->addPurchaseOrder($order);

        return $order;
    }

    /**
     * Receives units against an open purchase order, never more than are still expected of each SKU. The order it gives
     * back tells, through `line()`, what each unit that came cost and who owns it, when the order said so; with costing,
     * the units come in at that cost, an owner's units out of the shop's value.
     *
     * A landed cost — the freight, duties and brokerage of this receipt, in all — is shared out over the lines of the
     * shop's units with a unit cost, in proportion to their value, to the minor unit; it needs costing.
     *
     * @param array<int|string, int> $lines SKU → units that came; a numeric SKU is an integer key, as PHP makes it
     */
    public function receive(string $number, array $lines, DateTimeImmutable $at, ?Money $landedCost = null): PurchaseOrder
    {
        $order = $this->find($number);
        $bearers = [];

        foreach ($lines as $sku => $quantity) {
            $sku = (string) $sku;
            $order->receive($sku, $quantity);
            $line = $order->line($sku);
            $this->stock->receive($sku, $order->location, $quantity, $at, $order->number, $line?->unitCost, $line?->owner);

            if ($line?->unitCost instanceof Money && null === $line->owner) {
                $bearers[] = [$sku, $line->unitCost->multiply($quantity)->minorUnits, $quantity];
            }
        }

        if ($landedCost instanceof Money) {
            $costing = $this->costing ?? throw CostRefused::notCosted();

            foreach (CostShare::landed($landedCost, $bearers, $order->number) as [$sku, $share]) {
                $costing->land($sku, $order->location, $share, $order->number, $at);
            }
        }

        return $order;
    }

    /**
     * Expects nothing more of an open purchase order.
     */
    public function close(string $number): void
    {
        $this->find($number)->close();
    }

    /**
     * The units of a SKU ordered from suppliers and not received yet.
     */
    public function onOrder(string $sku): int
    {
        return array_sum(array_map(static fn (PurchaseOrder $order): int => $order->outstanding($sku), $this->orders->openPurchaseOrders()));
    }

    private function find(string $number): PurchaseOrder
    {
        return $this->orders->findPurchaseOrder($number) ?? throw PurchaseRefused::unknown($number);
    }
}
