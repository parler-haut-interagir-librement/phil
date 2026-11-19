<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Fixture;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\Reservation\Interval;
use Phil\ReservationBundle\Domain\Contract\ReservationRepository;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Model\Reservation;
use Phil\ReservationBundle\Domain\Status\ReservationStatus;

/**
 * The reservations in an array; the lock records the resources it was taken on, and the spans `holding()` was asked.
 */
final class InMemoryReservations implements ReservationRepository
{
    /** @var array<string, Reservation> */
    public array $reservations = [];

    /** @var list<string> */
    public array $locked = [];

    /** @var list<Interval> */
    public array $asked = [];

    /** @var list<int> */
    public array $limits = [];

    public int $saves = 0;

    /** @var ?list<Reservation> what `expiredHolds()` lists instead of the holds it finds */
    public ?array $listedExpired = null;

    #[Override]
    public function get(string $id): ?Reservation
    {
        return $this->reservations[$id] ?? null;
    }

    #[Override]
    public function save(Reservation $reservation): void
    {
        $this->reservations[$reservation->id] = $reservation;
        ++$this->saves;
    }

    #[Override]
    public function holding(BookableResource $resource, Interval $span, DateTimeImmutable $now): array
    {
        $this->asked[] = $span;

        return array_values(array_filter($this->reservations, static fn (Reservation $reservation): bool => $reservation->resource() === $resource && $reservation->holdsUnits($now) && $reservation->interval()->overlaps($span)));
    }

    #[Override]
    public function between(DateTimeImmutable $from, DateTimeImmutable $until, int $limit, ?BookableResource $resource = null): array
    {
        $this->limits[] = $limit;
        $period = new Interval($from, $until);

        return array_values(array_filter($this->reservations, static fn (Reservation $reservation): bool => (!$resource instanceof BookableResource || $reservation->resource() === $resource) && \in_array($reservation->status(), [ReservationStatus::Held, ReservationStatus::Confirmed], true) && $reservation->interval()->overlaps($period)));
    }

    #[Override]
    public function expiredHolds(DateTimeImmutable $now, int $limit): array
    {
        $this->limits[] = $limit;

        return $this->listedExpired ?? array_values(array_filter($this->reservations, static fn (Reservation $reservation): bool => ReservationStatus::Held === $reservation->status() && !$reservation->holdsUnits($now)));
    }

    #[Override]
    public function upcoming(DateTimeImmutable $now, DateTimeImmutable $until, int $limit): array
    {
        $this->limits[] = $limit;
        $this->asked[] = new Interval($now, $until);

        return array_values(array_filter($this->reservations, static fn (Reservation $reservation): bool => ReservationStatus::Confirmed === $reservation->status() && $reservation->interval()->from > $now->getTimestamp() && $reservation->interval()->from <= $until->getTimestamp()));
    }

    #[Override]
    public function exclusively(BookableResource $resource, Closure $work): mixed
    {
        $this->locked[] = $resource->id;

        return $work();
    }
}
