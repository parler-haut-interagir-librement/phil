<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Contract;

use Phil\LocalPickup\Pickup;

/**
 * Puts the goods of an order nobody collected back in stock, at the agreed point's stock location: the application's
 * logistics does it, such as by releasing the order's reservation.
 *
 * **Must be idempotent**, keyed on the order number: a run stopped between this call and the pickup's save calls it
 * again for the same order.
 */
interface ExpiredPickupRestocker
{
    public function restock(Pickup $pickup): void;
}
