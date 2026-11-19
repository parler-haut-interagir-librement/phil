<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Domain\Contract;

use Closure;
use DateTimeImmutable;
use Phil\Reservation\Interval;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Model\Reservation;

/**
 * Where the reservations are kept, and the lock that makes the requests on one resource run one at a time.
 */
interface ReservationRepository
{
    public function get(string $id): ?Reservation;

    public function save(Reservation $reservation): void;

    /**
     * The reservations of a resource that keep their units at a moment — confirmed, or held and not expired — and
     * meet a span.
     *
     * @return list<Reservation> by start
     */
    public function holding(BookableResource $resource, Interval $span, DateTimeImmutable $now): array;

    /**
     * The reservations held or confirmed that meet a period, of one resource or of all, at most `$limit`.
     *
     * @return list<Reservation> by start
     */
    public function between(DateTimeImmutable $from, DateTimeImmutable $until, int $limit, ?BookableResource $resource = null): array;

    /**
     * The holds whose moment has come and that are not marked expired yet, at most `$limit`.
     *
     * @return list<Reservation> the oldest first
     */
    public function expiredHolds(DateTimeImmutable $now, int $limit): array;

    /**
     * The confirmed reservations that start after now and at the latest at `$until`, at most `$limit`.
     *
     * @return list<Reservation> by start
     */
    public function upcoming(DateTimeImmutable $now, DateTimeImmutable $until, int $limit): array;

    /**
     * Runs the work in a transaction that holds the resource's row: two requests on one resource run one after the
     * other, so that both cannot take the last unit.
     *
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     */
    public function exclusively(BookableResource $resource, Closure $work): mixed;
}
