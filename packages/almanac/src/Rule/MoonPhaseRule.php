<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Almanac\Astronomy\MoonPhase;
use Phil\Almanac\Astronomy\MoonPhases;
use Phil\Almanac\Astronomy\Phase;

/**
 * Every instant of a phase of the Moon in the year, in the time zone: `full moon`, `new moon`, `first quarter`,
 * `last quarter`.
 */
final readonly class MoonPhaseRule implements WithinTheYear
{
    public function __construct(
        public Phase $phase,
    ) {
    }

    #[Override]
    public function in(int $year, DateTimeZone $zone): array
    {
        $from = Days::of($year, 1, 1, $zone);

        return array_map(
            static fn (MoonPhase $moon): DateTimeImmutable => $moon->at->setTimezone($zone),
            MoonPhases::between($from, $from->modify('+1 year'), [$this->phase]),
        );
    }

    #[Override]
    public function isInstant(): bool
    {
        return true;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->phase->value;
    }
}
