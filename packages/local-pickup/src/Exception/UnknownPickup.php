<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Exception;

use RuntimeException;

/**
 * No pickup is kept for the order.
 */
final class UnknownPickup extends RuntimeException implements LocalPickupException
{
    public static function forOrder(string $order): self
    {
        return new self(\sprintf('No pickup is kept for the order "%s".', $order));
    }
}
