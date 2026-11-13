<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * An event was built, or read back from a store, with a value it cannot hold.
 */
final class InvalidEvent extends InvalidArgumentException implements ErrorTrackingException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
