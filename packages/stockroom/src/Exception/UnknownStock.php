<?php

declare(strict_types=1);

namespace Phil\Stockroom\Exception;

use OutOfBoundsException;

/**
 * A container, an item or a load list that the stores do not hold.
 */
final class UnknownStock extends OutOfBoundsException implements StockroomException
{
    public static function container(string $id): self
    {
        return new self(\sprintf('There is no container "%s".', $id));
    }

    public static function item(string $id): self
    {
        return new self(\sprintf('There is no item "%s".', $id));
    }

    public static function loadList(string $event): self
    {
        return new self(\sprintf('The event "%s" has no load list.', $event));
    }
}
