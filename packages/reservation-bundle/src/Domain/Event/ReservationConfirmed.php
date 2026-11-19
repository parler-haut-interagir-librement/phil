<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Domain\Event;

/**
 * A reservation confirmed, directly or from a hold. It carries identifiers only: a listener reads the rest.
 */
final readonly class ReservationConfirmed
{
    public function __construct(
        public string $reservationId,
        public string $resourceId,
    ) {
    }
}
