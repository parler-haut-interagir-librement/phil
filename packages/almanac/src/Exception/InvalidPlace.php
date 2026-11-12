<?php

declare(strict_types=1);

namespace Phil\Almanac\Exception;

use InvalidArgumentException;

/**
 * A latitude or a longitude out of its range.
 */
final class InvalidPlace extends InvalidArgumentException implements AlmanacException
{
    public static function coordinate(string $which, float $value, int $limit): self
    {
        return new self(\sprintf('A %s is between -%d and %d degrees, not %.10g.', $which, $limit, $limit, $value));
    }
}
