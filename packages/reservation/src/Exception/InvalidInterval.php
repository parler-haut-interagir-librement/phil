<?php

declare(strict_types=1);

namespace Phil\Reservation\Exception;

use InvalidArgumentException;

/**
 * A span of time that cannot be: an end that does not come after its start, or a span longer than the library reads.
 */
final class InvalidInterval extends InvalidArgumentException implements ReservationException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
