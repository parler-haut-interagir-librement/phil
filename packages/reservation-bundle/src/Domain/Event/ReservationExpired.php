<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Domain\Event;

/**
 * A hold that expired unconfirmed: its units are free again. It carries identifiers only: a listener reads the rest.
 */
final readonly class ReservationExpired
{
    public function __construct(
        public string $reservationId,
        public string $resourceId,
    ) {
    }
}
