<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Contract;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;

/**
 * When an order, or one of its lines, left the store: what a returns module counts its window from. The logistics
 * module, or the application, aliases its own implementation; the bundle's `UnknownShipments` knows of none.
 */
interface OrderShipments
{
    /**
     * When the order's goods were shipped or handed over — the line's when a line is given, since an order may leave
     * in several parcels; `null` when it has not left yet, or the implementation does not know.
     */
    public function shippedAt(string $orderNumber, ?SellableReference $line = null): ?DateTimeImmutable;
}
