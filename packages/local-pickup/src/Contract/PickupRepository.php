<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Contract;

use DateTimeImmutable;
use Phil\LocalPickup\Pickup;
use Phil\Reservation\Booking;
use Phil\Reservation\Interval;

/**
 * Where the pickups are kept: what Pickup::toArray() gives, read back with Pickup::fromArray().
 *
 * Booking a slot reads the bookings and then saves: two customers taking the last place of a slot at once must be put
 * one after the other, by a lock on the pickup point around Pickups::place() — the application's, such as
 * symfony/lock.
 */
interface PickupRepository
{
    public function find(string $order): ?Pickup;

    public function save(Pickup $pickup): void;

    /**
     * The pickups of a point that still hold a place — being prepared or ready — whose slot meets the span, as
     * phil/reservation bookings named by their order number.
     *
     * @return list<Booking>
     */
    public function bookings(string $point, Interval $span): array;

    /**
     * The order numbers of the ready pickups whose hold period has ended at that moment, oldest end first.
     *
     * @return list<string>
     */
    public function overdue(DateTimeImmutable $at, int $limit): array;

    /**
     * The pickups of a customer, for the export of their personal data.
     *
     * @return list<Pickup>
     */
    public function ofCustomer(string $customer): array;
}
