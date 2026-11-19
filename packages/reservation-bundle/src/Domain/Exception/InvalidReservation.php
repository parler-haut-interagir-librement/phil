<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Domain\Exception;

use DomainException;
use Phil\Reservation\Exception\ReservationException;

/**
 * A resource or a reservation given a value it cannot take, or asked for a change its state does not allow.
 */
final class InvalidReservation extends DomainException implements ReservationException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
