<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A place, a tile source, a grid, a booth or a floor plan that a map cannot be made of.
 */
final class InvalidMap extends InvalidArgumentException implements MapException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
