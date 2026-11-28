<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Exception;

use InvalidArgumentException;

/**
 * A bound of the reader, or a delay of the refresh policy, outside the range the library accepts.
 */
final class InvalidLimits extends InvalidArgumentException implements CalendarImportException
{
    public static function outOfRange(string $what, int $value, int $min, int $max): self
    {
        return new self(\sprintf('%s is %d to %d, %d given.', $what, $min, $max, $value));
    }
}
