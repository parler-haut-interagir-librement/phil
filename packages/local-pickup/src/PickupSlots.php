<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use DateTimeImmutable;
use Phil\LocalPickup\Contract\PickupRepository;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Decision;
use Phil\Reservation\Interval;
use Phil\Reservation\Violation;
use Phil\Reservation\ViolationCode;

/**
 * The slots of a pickup point, read through phil/reservation: open, following the point's rules, with a place left
 * among the orders already booked, and no sooner than the preparation time after now.
 */
final readonly class PickupSlots
{
    public function __construct(private PickupRepository $pickups)
    {
    }

    /**
     * The slots of a period a customer may choose now.
     *
     * @return list<Interval>
     */
    public function available(PickupPoint $point, Interval $period, DateTimeImmutable $now, int $limit = 100): array
    {
        $earliest = $now->getTimestamp() + $point->preparationMinutes * 60;
        $from = max($period->from, $earliest);

        if ($from >= $period->until) {
            return [];
        }

        $span = Interval::ofTimestamps($from, $period->until, $point->slots->timezone);

        return $point->slots->slots($span, $point->slotMinutes, $this->pickups->bookings($point->code, $this->around($point, $span)), $now, limit: $limit);
    }

    /**
     * Whether the slot can be booked now, and why not: phil/reservation's reasons, and `TooSoon` with the preparation
     * time in minutes when the order could not be ready by its start.
     */
    public function check(PickupPoint $point, Interval $slot, DateTimeImmutable $now): Decision
    {
        $decision = $point->slots->check(new BookingRequest($slot, $now), $this->pickups->bookings($point->code, $this->around($point, $slot)));

        if ($slot->from >= $now->getTimestamp() + $point->preparationMinutes * 60) {
            return $decision;
        }

        return new Decision([...$decision->violations, new Violation(ViolationCode::TooSoon, ['minutes' => $point->preparationMinutes])], $decision->conflicts);
    }

    /**
     * The span whose bookings can meet the given one, buffers included.
     */
    private function around(PickupPoint $point, Interval $span): Interval
    {
        $buffers = $point->slots->bufferBefore + $point->slots->bufferAfter;

        return $span->widen($buffers, $buffers);
    }
}
