<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Domain\Event;

/**
 * A reservation held: its units are kept until the hold expires or it is confirmed. It carries identifiers only: a listener reads the rest.
 */
final readonly class ReservationHeld
{
    public function __construct(
        public string $reservationId,
        public string $resourceId,
    ) {
    }
}
