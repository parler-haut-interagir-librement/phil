<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Domain\Exception\PurchaseRefused;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\PurchaseOrder;
use Phil\CommerceLogisticsBundle\Domain\Repository\LocationRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\PurchaseOrderRepository;

/**
 * Goods ordered from suppliers and received against their purchase orders: each receipt adds the units to stock at the
 * order's location, in the journal under the order's number. Nothing is committed here.
 */
final readonly class Purchasing
{
    public function __construct(
        private PurchaseOrderRepository $orders,
        private LocationRepository $locations,
        private Stock $stock,
    ) {
    }

    /**
     * @param array<int|string, int> $lines SKU → units ordered; a numeric SKU is an integer key, as PHP makes it
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
     * Receives units against an open purchase order, never more than are still expected of each SKU.
     *
     * @param array<int|string, int> $lines SKU → units that came; a numeric SKU is an integer key, as PHP makes it
     */
    public function receive(string $number, array $lines, DateTimeImmutable $at): PurchaseOrder
    {
        $order = $this->find($number);

        foreach ($lines as $sku => $quantity) {
            $order->receive((string) $sku, $quantity);
            $this->stock->receive((string) $sku, $order->location, $quantity, $at, $order->number);
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
