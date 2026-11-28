<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Contract;

use Phil\LocalPickup\Pickup;

/**
 * Cancels and refunds an order nobody collected: the application's commerce does it.
 *
 * **Must be idempotent**, keyed on the order number: a run stopped between this call and the pickup's save calls it
 * again for the same order.
 */
interface ExpiredPickupCanceller
{
    public function cancelAndRefund(Pickup $pickup): void;
}
