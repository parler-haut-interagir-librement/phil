<?php

declare(strict_types=1);

namespace Phil\Reservation;

use DateTimeImmutable;

/**
 * A booking asked for: its span, how many units, when it is asked, and the booking it replaces when it moves one.
 */
final readonly class BookingRequest
{
    /**
     * @param ?string $replacing the identifier of a booking left out of the conflicts: the one being moved or resized
     */
    public function __construct(
        public Interval $interval,
        public DateTimeImmutable $requestedAt,
        public int $quantity = 1,
        public ?string $replacing = null,
    ) {
    }
}
