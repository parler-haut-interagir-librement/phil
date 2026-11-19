<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Application\Message;

/**
 * The Messenger message of a reminder due: identifiers only, the handler of the application reads the rest.
 */
final readonly class SendReservationReminder
{
    public function __construct(
        public string $reservationId,
        public int $offset,
    ) {
    }
}
