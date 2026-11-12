<?php

declare(strict_types=1);

namespace Phil\Almanac\Exception;

use OutOfRangeException;

/**
 * A year outside the range a computation is valid for.
 */
final class OutOfRange extends OutOfRangeException implements AlmanacException
{
    public static function year(int $year, string $what, int $from, int $through): self
    {
        return new self(\sprintf('%s is computed for the years %d to %d, not %d.', $what, $from, $through, $year));
    }
}
