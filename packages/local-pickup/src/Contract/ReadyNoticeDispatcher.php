<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Contract;

/**
 * Asks, later and in a worker, for the "ready for pickup" notice of an order: a NotifyPickupReady message on the
 * application's bus, which carries the order number and nothing else.
 */
interface ReadyNoticeDispatcher
{
    public function dispatch(string $order): void;
}
