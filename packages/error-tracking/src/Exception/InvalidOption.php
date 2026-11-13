<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Exception;

use InvalidArgumentException;

/**
 * A service of the library was given an option outside its range: a sample rate, a bound.
 */
final class InvalidOption extends InvalidArgumentException implements ErrorTrackingException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
