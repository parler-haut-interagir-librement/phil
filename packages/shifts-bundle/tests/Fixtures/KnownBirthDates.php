<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Fixtures;

use DateTimeImmutable;
use Override;
use Phil\ShiftsBundle\Application\Contract\BirthDates;

/**
 * The birth dates of the volunteers of the tests, by identifier.
 */
final class KnownBirthDates implements BirthDates
{
    /**
     * @param array<string, string> $dates
     */
    public function __construct(
        public array $dates = [],
    ) {
    }

    #[Override]
    public function of(string $volunteer): ?DateTimeImmutable
    {
        return isset($this->dates[$volunteer]) ? new DateTimeImmutable($this->dates[$volunteer]) : null;
    }
}
