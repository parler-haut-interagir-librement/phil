<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Application;

use Phil\ReservationBundle\Application\Contract\ReminderSender;
use Phil\ReservationBundle\Domain\Contract\ReservationRepository;
use Phil\ReservationBundle\Domain\Contract\ResourceRepository;
use Psr\Clock\ClockInterface;

/**
 * Sends the reminders due: for each confirmed reservation that starts within the furthest reminder of the resources,
 * the one its resource's schedule says is due, once. The reminder is recorded before it is sent, so that a sender
 * that fails loses it rather than sends it twice. Run it every few minutes; each run reads at most `$limit`
 * reservations, the soonest first.
 */
final readonly class Reminders
{
    public function __construct(
        private ResourceRepository $resources,
        private ReservationRepository $reservations,
        private ReminderSender $sender,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int how many reminders were sent
     */
    public function send(int $limit = 500): int
    {
        $furthest = 0;

        foreach ($this->resources->all() as $resource) {
            foreach ($resource->reminders() as $minutes) {
                $furthest = max($furthest, $minutes);
            }
        }

        if (0 === $furthest) {
            return 0;
        }

        $now = $this->clock->now();
        $sent = 0;

        foreach ($this->reservations->upcoming($now, $now->modify(\sprintf('+%d minutes', $furthest)), $limit) as $reservation) {
            $due = $reservation->resource()->reminderSchedule()->due($reservation->interval(), $now, $reservation->lastReminder());

            if (null !== $due) {
                $reservation->remind($due);
                $this->reservations->save($reservation);
                $this->sender->send($reservation->id, $due);
                ++$sent;
            }
        }

        return $sent;
    }
}
