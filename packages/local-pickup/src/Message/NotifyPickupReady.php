<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Message;

/**
 * The message that asks for an order's "ready for pickup" notice: the order number and nothing else, so that a queue
 * never holds a code nor a person's data.
 */
final readonly class NotifyPickupReady
{
    public function __construct(public string $order)
    {
    }
}
