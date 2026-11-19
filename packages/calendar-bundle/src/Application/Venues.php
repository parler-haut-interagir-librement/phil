<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\Repository\VenueRepository;

/**
 * The calendar's venues: a place of its own, or a listing of the application's directory.
 */
final readonly class Venues
{
    public function __construct(
        private VenueRepository $venues,
    ) {
    }

    public function create(VenueDraft $draft): Venue
    {
        $venue = new Venue((string) $draft->name);
        $this->apply($venue, $draft);

        return $venue;
    }

    public function apply(Venue $venue, VenueDraft $draft): void
    {
        $venue->rename((string) $draft->name);
        $venue->locate($draft->room, $draft->address, $draft->latitude, $draft->longitude);
        [$kind, $reference] = null === $draft->listing ? [null, null] : explode(':', $draft->listing, 2);
        $venue->listAs($kind, $reference);
        $this->venues->save($venue);
    }

    /**
     * The venue of a listing, made from its name the first time it is asked for: the directory's places become the
     * calendar's without being typed twice.
     */
    public function ofListing(string $kind, string $reference, string $name): Venue
    {
        $venue = $this->venues->ofListing($kind, $reference);

        if ($venue instanceof Venue) {
            return $venue;
        }

        $venue = new Venue($name);
        $venue->listAs($kind, $reference);

        $this->venues->save($venue);

        return $venue;
    }

    public function remove(Venue $venue): void
    {
        $this->venues->remove($venue);
    }

    /**
     * @return list<Venue>
     */
    public function all(): array
    {
        return $this->venues->all();
    }
}
