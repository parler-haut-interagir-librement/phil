<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeZone;
use Override;
use Phil\Almanac\Astronomy\Season;
use Phil\Almanac\Astronomy\Seasons;

/**
 * The instant of an equinox or a solstice, in the time zone: `march equinox`, `june solstice`, `september equinox`,
 * `december solstice`.
 */
final readonly class SeasonRule implements WithinTheYear
{
    public function __construct(
        public Season $season,
    ) {
    }

    #[Override]
    public function in(int $year, DateTimeZone $zone): array
    {
        return [Seasons::of($year, $this->season)->setTimezone($zone)];
    }

    #[Override]
    public function isInstant(): bool
    {
        return true;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->season->value;
    }
}
