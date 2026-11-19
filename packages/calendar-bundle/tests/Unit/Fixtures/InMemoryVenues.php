<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Fixtures;

use Override;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\Repository\VenueRepository;

/**
 * Venues in memory, for the tests of what reads them.
 */
final class InMemoryVenues implements VenueRepository
{
    /** @var array<string, Venue> */
    public array $venues = [];

    #[Override]
    public function save(Venue $venue): void
    {
        $this->venues[$venue->id] = $venue;
    }

    #[Override]
    public function remove(Venue $venue): void
    {
        unset($this->venues[$venue->id]);
    }

    #[Override]
    public function get(string $id): ?Venue
    {
        return $this->venues[$id] ?? null;
    }

    #[Override]
    public function all(): array
    {
        $venues = array_values($this->venues);
        usort($venues, static fn (Venue $a, Venue $b): int => [$a->name(), $a->room()] <=> [$b->name(), $b->room()]);

        return $venues;
    }

    #[Override]
    public function ofListing(string $kind, string $reference): ?Venue
    {
        foreach ($this->venues as $venue) {
            if ($venue->listing() === $kind.':'.$reference) {
                return $venue;
            }
        }

        return null;
    }
}
