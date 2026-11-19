<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Application;

use DateTimeImmutable;

/**
 * What the reservation form fills: a start, an end and the units asked for.
 */
final class ReservationDraft
{
    public function __construct(
        public ?DateTimeImmutable $start = null,
        public ?DateTimeImmutable $end = null,
        public int $quantity = 1,
    ) {
    }
}
