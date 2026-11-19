<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application\Contract;

use DateTimeImmutable;
use Phil\MapBundle\Domain\Enum\BoothState;
use Phil\MapBundle\Domain\ValueObject\Booth;

/**
 * What the booths of a floor plan are over a period: free, taken, closed or unknown. The bundle's adapter asks
 * phil/reservation-bundle about the resource each booth names; an application with its own bookings implements it.
 *
 * Invariants: every booth given has a state in the answer, under its identifier; a booth without a resource is
 * unknown; nothing is booked or held.
 */
interface BoothAvailability
{
    /**
     * @param list<Booth> $booths
     *
     * @return array<string, BoothState> by the booth's identifier
     */
    public function states(array $booths, DateTimeImmutable $start, DateTimeImmutable $end): array;
}
