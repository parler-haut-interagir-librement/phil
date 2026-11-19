<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Domain\Event;

/**
 * A reservation cancelled: its units are free again. It carries identifiers only: a listener reads the rest.
 */
final readonly class ReservationCancelled
{
    public function __construct(
        public string $reservationId,
        public string $resourceId,
    ) {
    }
}
