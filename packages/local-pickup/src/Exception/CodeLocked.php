<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Exception;

use RuntimeException;

/**
 * The order's code took as many wrong attempts as allowed: no code is read any more until a new one is sent.
 */
final class CodeLocked extends RuntimeException implements LocalPickupException
{
    public static function forOrder(string $order): self
    {
        return new self(\sprintf('The pickup code of the order "%s" is locked after too many wrong attempts; send a new one.', $order));
    }
}
