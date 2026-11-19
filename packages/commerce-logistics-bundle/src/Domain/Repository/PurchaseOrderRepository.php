<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Repository;

use Phil\CommerceLogisticsBundle\Domain\Model\PurchaseOrder;

/**
 * Finds and stores purchase orders. `Infrastructure\Doctrine\DoctrineLogisticsRepository` is the Doctrine adapter.
 */
interface PurchaseOrderRepository
{
    public function findPurchaseOrder(string $number): ?PurchaseOrder;

    public function addPurchaseOrder(PurchaseOrder $order): void;

    /**
     * @return list<PurchaseOrder> the orders still open, the earliest ordered first
     */
    public function openPurchaseOrders(): array;
}
