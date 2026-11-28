<?php

declare(strict_types=1);

namespace Phil\Shifts\Exception;

use InvalidArgumentException;

/**
 * A shift, a place or a swap offer built with a value outside its bounds: an identifier of the wrong shape, a window
 * that ends before it starts, a capacity of zero. The message names the field, never its value.
 */
final class InvalidShift extends InvalidArgumentException implements ShiftsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
