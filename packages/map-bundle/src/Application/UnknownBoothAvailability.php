<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application;

use DateTimeImmutable;
use Override;
use Phil\MapBundle\Application\Contract\BoothAvailability;
use Phil\MapBundle\Domain\Enum\BoothState;

/**
 * Every booth unknown: the availability without phil/reservation-bundle nor one of the application's.
 */
final readonly class UnknownBoothAvailability implements BoothAvailability
{
    #[Override]
    public function states(array $booths, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $states = [];

        foreach ($booths as $booth) {
            $states[$booth->id] = BoothState::Unknown;
        }

        return $states;
    }
}
