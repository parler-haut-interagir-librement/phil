<?php

declare(strict_types=1);

namespace Phil\Reservation\Exception;

use InvalidArgumentException;

/**
 * A resource, a rule, a booking or a reminder schedule defined with a value it cannot take: a capacity below one, a
 * negative buffer, opening hours that overlap, an empty identifier.
 */
final class InvalidDefinition extends InvalidArgumentException implements ReservationException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
