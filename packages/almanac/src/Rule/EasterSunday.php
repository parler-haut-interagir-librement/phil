<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeZone;
use Override;
use Phil\Almanac\Easter;

/**
 * Easter Sunday — `easter` — or its Orthodox date — `orthodox easter`. With an offset, the days around it:
 * `easter - 47 days` is Mardi Gras.
 */
final readonly class EasterSunday implements WithinTheYear
{
    public function __construct(
        public bool $orthodox = false,
    ) {
    }

    #[Override]
    public function in(int $year, DateTimeZone $zone): array
    {
        return [$this->orthodox ? Easter::orthodox($year, $zone) : Easter::western($year, $zone)];
    }

    #[Override]
    public function isInstant(): bool
    {
        return false;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->orthodox ? 'orthodox easter' : 'easter';
    }
}
