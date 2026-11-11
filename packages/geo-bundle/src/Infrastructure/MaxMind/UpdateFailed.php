<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Infrastructure\MaxMind;

use RuntimeException;
use Throwable;

/**
 * A MaxMind database that could not be updated; the database in place is kept as it was.
 */
final class UpdateFailed extends RuntimeException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The MaxMind database was not updated: %s', $reason), previous: $previous);
    }
}
