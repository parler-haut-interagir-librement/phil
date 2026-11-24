<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Exception;

use InvalidArgumentException;

/**
 * A quantity, a rate, a code or a setting outside what the library accepts.
 */
final class InvalidInput extends InvalidArgumentException implements InventoryAlertsException
{
    public static function because(string $field, string $rule): self
    {
        return new self(\sprintf('The %s %s.', $field, $rule));
    }
}
