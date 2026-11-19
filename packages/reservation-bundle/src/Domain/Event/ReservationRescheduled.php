<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Domain\Event;

/**
 * A reservation moved or resized. It carries identifiers only: a listener reads the rest.
 */
final readonly class ReservationRescheduled
{
    public function __construct(
        public string $reservationId,
        public string $resourceId,
    ) {
    }
}
