<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Application\Exception;

use Phil\Reservation\Exception\ReservationException;
use RuntimeException;

/**
 * A resource or a reservation that does not exist, or a resource that takes no reservation.
 */
final class NotFound extends RuntimeException implements ReservationException
{
    public static function resource(string $id): self
    {
        return new self(\sprintf('No active resource "%s".', mb_substr($id, 0, 64)));
    }

    public static function reservation(string $id): self
    {
        return new self(\sprintf('No reservation "%s".', mb_substr($id, 0, 64)));
    }
}
