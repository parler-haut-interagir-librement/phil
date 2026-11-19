<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Application\Contract;

/**
 * Sends a reminder of a reservation, by identifiers: the application reads the reservation and its holder, and writes
 * to them as it does.
 */
interface ReminderSender
{
    /**
     * @param int $offset the minutes before the start this reminder comes
     */
    public function send(string $reservationId, int $offset): void;
}
