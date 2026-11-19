<?php

declare(strict_types=1);

namespace Phil\MapBundle\Infrastructure\Reservation;

use DateTimeImmutable;
use Override;
use Phil\MapBundle\Application\Contract\BoothAvailability;
use Phil\MapBundle\Domain\Enum\BoothState;
use Phil\Reservation\ViolationCode;
use Phil\ReservationBundle\Application\Exception\NotFound;
use Phil\ReservationBundle\Application\Reservations;

/**
 * The booths' states from phil/reservation-bundle: each booth's resource is asked whether one unit could be booked
 * over the period, without booking it. Accepted is free; refused because full is taken; refused for another reason —
 * closed then, too soon, off the grid — is closed; a resource that does not exist, or a booth without one, is unknown.
 */
final readonly class ReservationBoothAvailability implements BoothAvailability
{
    public function __construct(
        private Reservations $reservations,
    ) {
    }

    #[Override]
    public function states(array $booths, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $states = [];

        foreach ($booths as $booth) {
            $states[$booth->id] = null === $booth->resource ? BoothState::Unknown : $this->state($booth->resource, $start, $end);
        }

        return $states;
    }

    private function state(string $resource, DateTimeImmutable $start, DateTimeImmutable $end): BoothState
    {
        try {
            $decision = $this->reservations->check($resource, $start, $end);
        } catch (NotFound) {
            return BoothState::Unknown;
        }

        return match (true) {
            $decision->allowed() => BoothState::Free,
            $decision->has(ViolationCode::Full) => BoothState::Taken,
            default => BoothState::Closed,
        };
    }
}
