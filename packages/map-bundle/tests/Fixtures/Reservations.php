<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Fixtures;

use Closure;
use DateTimeImmutable;
use Phil\Reservation\Interval;
use Phil\ReservationBundle\Domain\Contract\ReservationRepository;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Model\Reservation;

/**
 * The reservations of phil/reservation-bundle in an array: what the check reads, nothing more.
 */
final class Reservations implements ReservationRepository
{
    /** @var array<string, Reservation> */
    private array $reservations = [];

    public function get(string $id): ?Reservation
    {
        return $this->reservations[$id] ?? null;
    }

    public function save(Reservation $reservation): void
    {
        $this->reservations[$reservation->id] = $reservation;
    }

    public function holding(BookableResource $resource, Interval $span, DateTimeImmutable $now): array
    {
        return array_values(array_filter($this->reservations, static fn (Reservation $reservation): bool => $reservation->resource() === $resource && $reservation->holdsUnits($now) && $reservation->interval()->overlaps($span)));
    }

    public function between(DateTimeImmutable $from, DateTimeImmutable $until, int $limit, ?BookableResource $resource = null): array
    {
        return [];
    }

    public function expiredHolds(DateTimeImmutable $now, int $limit): array
    {
        return [];
    }

    public function upcoming(DateTimeImmutable $now, DateTimeImmutable $until, int $limit): array
    {
        return [];
    }

    public function exclusively(BookableResource $resource, Closure $work): mixed
    {
        return $work();
    }
}
