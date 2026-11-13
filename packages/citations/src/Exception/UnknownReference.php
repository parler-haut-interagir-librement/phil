<?php

declare(strict_types=1);

namespace Phil\Citations\Exception;

use OutOfBoundsException;

/**
 * A citation names an id that no reference of the list holds.
 */
final class UnknownReference extends OutOfBoundsException implements CitationsException
{
    public static function id(string $id): self
    {
        return new self(\sprintf('No reference has the id "%s".', $id));
    }
}
