<?php

declare(strict_types=1);

namespace Phil\Stockroom\Exception;

use InvalidArgumentException;

/**
 * A container, an item, a place, a movement or a load list given a value it cannot take: an empty name, a quantity
 * out of bounds, an identifier that looks like an e-mail address, a container placed inside itself.
 */
final class InvalidStock extends InvalidArgumentException implements StockroomException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
