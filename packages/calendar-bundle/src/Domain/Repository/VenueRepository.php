<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Repository;

use Phil\CalendarBundle\Domain\Model\Venue;

/**
 * The venues of the calendar.
 */
interface VenueRepository
{
    public function save(Venue $venue): void;

    public function remove(Venue $venue): void;

    public function get(string $id): ?Venue;

    /**
     * @return list<Venue> by name, then room
     */
    public function all(): array;

    /**
     * The venue a listing of the directory is, when there is one.
     */
    public function ofListing(string $kind, string $reference): ?Venue;
}
