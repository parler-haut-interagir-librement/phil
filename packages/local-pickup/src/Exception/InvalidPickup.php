<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Exception;

use InvalidArgumentException;

/**
 * An identifier, a setting or a stored row outside what the library accepts.
 */
final class InvalidPickup extends InvalidArgumentException implements LocalPickupException
{
    public static function because(string $field, string $rule): self
    {
        return new self(\sprintf('The %s %s.', $field, $rule));
    }
}
