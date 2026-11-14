<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Phil\Idempotency\IdempotencyReservation as Reservation;

/**
 * The reservation a request took, kept on the request until its response is known, with the retention, in seconds,
 * its answer is kept for.
 */
final readonly class IdempotencyReservation
{
    public function __construct(
        public Reservation $reservation,
        public int $ttl,
    ) {
    }
}
