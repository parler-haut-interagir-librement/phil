<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Application;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Reservation\Bookable;
use Phil\Reservation\Booking;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Decision;
use Phil\Reservation\Interval;
use Phil\ReservationBundle\Application\Exception\NotFound;
use Phil\ReservationBundle\Application\Exception\ReservationRefused;
use Phil\ReservationBundle\Domain\Contract\ReservationRepository;
use Phil\ReservationBundle\Domain\Contract\ResourceRepository;
use Phil\ReservationBundle\Domain\Event\ReservationCancelled;
use Phil\ReservationBundle\Domain\Event\ReservationConfirmed;
use Phil\ReservationBundle\Domain\Event\ReservationExpired;
use Phil\ReservationBundle\Domain\Event\ReservationHeld;
use Phil\ReservationBundle\Domain\Event\ReservationRescheduled;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Model\Reservation;
use Phil\ReservationBundle\Domain\Status\ReservationStatus;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Holds, books, confirms, moves and cancels reservations. Each request that takes units runs under the resource's
 * lock: it reads the reservations that hold units, asks `phil/reservation` whether it can be booked, and saves it,
 * before another request on the same resource reads anything. The events go out once the change is saved.
 */
final readonly class Reservations
{
    public function __construct(
        private ResourceRepository $resources,
        private ReservationRepository $reservations,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Whether a request could be booked now, without booking it.
     *
     * @param ?string $replacing the reservation being moved, left out of the conflicts
     *
     * @throws NotFound when the resource does not exist or takes no reservation
     */
    public function check(string $resourceId, DateTimeInterface $start, DateTimeInterface $end, int $quantity = 1, ?string $replacing = null): Decision
    {
        $resource = $this->resource($resourceId);

        return $this->decide($resource, $this->interval($resource, $start, $end), $quantity, $replacing, $this->now());
    }

    /**
     * Units kept for the resource's hold time, until `confirm()`.
     *
     * @throws NotFound           when the resource does not exist or takes no reservation
     * @throws ReservationRefused when the resource refuses the request
     */
    public function hold(string $resourceId, DateTimeInterface $start, DateTimeInterface $end, int $quantity = 1, ?string $holder = null): Reservation
    {
        return $this->take($resourceId, $start, $end, $quantity, $holder, true);
    }

    /**
     * Units booked at once, without a hold.
     *
     * @throws NotFound           when the resource does not exist or takes no reservation
     * @throws ReservationRefused when the resource refuses the request
     */
    public function book(string $resourceId, DateTimeInterface $start, DateTimeInterface $end, int $quantity = 1, ?string $holder = null): Reservation
    {
        return $this->take($resourceId, $start, $end, $quantity, $holder, false);
    }

    /**
     * Confirms a hold. A hold that expired is checked again first, and confirmed when its units are still free.
     *
     * @throws NotFound           when the reservation does not exist
     * @throws ReservationRefused when an expired hold's units were taken meanwhile
     */
    public function confirm(string $id): Reservation
    {
        $reservation = $this->reservation($id);
        $resource = $reservation->resource();

        $this->reservations->exclusively($resource, function () use ($reservation, $resource): void {
            $now = $this->now();

            if (!$reservation->holdsUnits($now) && \in_array($reservation->status(), [ReservationStatus::Held, ReservationStatus::Expired], true)) {
                $this->assertAllowed($this->decide($resource, $reservation->interval(), $reservation->quantity(), $reservation->id, $now, $reservation->createdAt()));
            }

            $reservation->confirm();
            $this->reservations->save($reservation);
        });

        $this->dispatch(new ReservationConfirmed($reservation->id, $resource->id));

        return $reservation;
    }

    /**
     * Moves or resizes a reservation held or confirmed, checked as a new request without itself.
     *
     * @throws NotFound           when the reservation does not exist
     * @throws ReservationRefused when the resource refuses the new span or quantity
     */
    public function reschedule(string $id, DateTimeInterface $start, DateTimeInterface $end, ?int $quantity = null): Reservation
    {
        $reservation = $this->reservation($id);
        $resource = $reservation->resource();
        $interval = $this->interval($resource, $start, $end);
        $quantity ??= $reservation->quantity();

        $this->reservations->exclusively($resource, function () use ($reservation, $resource, $interval, $quantity): void {
            $this->assertAllowed($this->decide($resource, $interval, $quantity, $reservation->id, $this->now()));
            $reservation->reschedule($interval, $quantity);
            $this->reservations->save($reservation);
        });

        $this->dispatch(new ReservationRescheduled($reservation->id, $resource->id));

        return $reservation;
    }

    /**
     * Frees a reservation's units.
     *
     * @throws NotFound when the reservation does not exist
     */
    public function cancel(string $id): Reservation
    {
        $reservation = $this->reservation($id);
        $reservation->cancel();

        $this->reservations->save($reservation);
        $this->dispatch(new ReservationCancelled($reservation->id, $reservation->resource()->id));

        return $reservation;
    }

    /**
     * The free slots of a period that a request asked for now would get.
     *
     * @return list<Interval>
     *
     * @throws NotFound when the resource does not exist or takes no reservation
     */
    public function slots(string $resourceId, DateTimeInterface $from, DateTimeInterface $until, int $minutes, int $quantity = 1, ?int $step = null, int $limit = 100): array
    {
        $resource = $this->resource($resourceId);
        $bookable = $resource->bookable();
        $period = $this->interval($resource, $from, $until);
        $now = $this->now();

        return $bookable->slots($period, $minutes, $this->bookings($resource, $bookable, $period, $now), $now, $quantity, $step, $limit);
    }

    /**
     * Marks the holds whose moment has come as expired, at most `$limit` at once.
     *
     * @return int how many expired
     */
    public function expireHolds(int $limit = 500): int
    {
        $now = $this->now();
        $expired = 0;

        foreach ($this->reservations->expiredHolds($now, $limit) as $reservation) {
            if ($reservation->expire($now)) {
                $this->reservations->save($reservation);
                $this->dispatch(new ReservationExpired($reservation->id, $reservation->resource()->id));
                ++$expired;
            }
        }

        return $expired;
    }

    private function take(string $resourceId, DateTimeInterface $start, DateTimeInterface $end, int $quantity, ?string $holder, bool $hold): Reservation
    {
        $resource = $this->resource($resourceId);
        $interval = $this->interval($resource, $start, $end);

        $reservation = $this->reservations->exclusively($resource, function () use ($resource, $interval, $quantity, $holder, $hold): Reservation {
            $now = $this->now();
            $this->assertAllowed($this->decide($resource, $interval, $quantity, null, $now));
            $reservation = $hold
                ? Reservation::hold($resource, $interval, $quantity, $holder, $now)
                : Reservation::confirmed($resource, $interval, $quantity, $holder, $now);
            $this->reservations->save($reservation);

            return $reservation;
        });

        $this->dispatch($hold ? new ReservationHeld($reservation->id, $resource->id) : new ReservationConfirmed($reservation->id, $resource->id));

        return $reservation;
    }

    /**
     * @param ?DateTimeImmutable $asked when the request was made, for a request checked again: the notice and the booking
     *                                  window then count from that moment instead of now
     */
    private function decide(BookableResource $resource, Interval $interval, int $quantity, ?string $replacing, DateTimeImmutable $now, ?DateTimeImmutable $asked = null): Decision
    {
        $bookable = $resource->bookable();

        return $bookable->check(new BookingRequest($interval, $asked ?? $now, $quantity, $replacing), $this->bookings($resource, $bookable, $interval, $now));
    }

    /**
     * The bookings that hold units and whose span, buffers included, may meet a span's: those that start or end
     * within both buffers of it.
     *
     * @return list<Booking>
     */
    private function bookings(BookableResource $resource, Bookable $bookable, Interval $span, DateTimeImmutable $now): array
    {
        $buffers = $bookable->bufferBefore + $bookable->bufferAfter;

        return array_map(static fn (Reservation $reservation): Booking => $reservation->booking(), $this->reservations->holding($resource, $span->widen($buffers, $buffers), $now));
    }

    private function assertAllowed(Decision $decision): void
    {
        if (!$decision->allowed()) {
            throw new ReservationRefused($decision);
        }
    }

    private function resource(string $id): BookableResource
    {
        $resource = $this->resources->get($id);

        if (!$resource instanceof BookableResource || !$resource->isActive()) {
            throw NotFound::resource($id);
        }

        return $resource;
    }

    private function reservation(string $id): Reservation
    {
        return $this->reservations->get($id) ?? throw NotFound::reservation($id);
    }

    private function interval(BookableResource $resource, DateTimeInterface $start, DateTimeInterface $end): Interval
    {
        $zone = $resource->timezone();

        return new Interval(DateTimeImmutable::createFromInterface($start)->setTimezone($zone), DateTimeImmutable::createFromInterface($end)->setTimezone($zone));
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }

    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
