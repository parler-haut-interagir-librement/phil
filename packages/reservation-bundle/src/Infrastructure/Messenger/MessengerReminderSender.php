<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Infrastructure\Messenger;

use Override;
use Phil\ReservationBundle\Application\Contract\ReminderSender;
use Phil\ReservationBundle\Application\Message\SendReservationReminder;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sends each reminder as a `SendReservationReminder` message on the bus: the application routes it to a transport and
 * handles it.
 */
final readonly class MessengerReminderSender implements ReminderSender
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    #[Override]
    public function send(string $reservationId, int $offset): void
    {
        $this->bus->dispatch(new SendReservationReminder($reservationId, $offset));
    }
}
