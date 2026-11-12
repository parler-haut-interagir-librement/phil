<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeZone;
use Override;
use Phil\Almanac\ChineseNewYear;

/**
 * The Chinese New Year — `chinese new year` —: the day China celebrates it, the same date in the time zone.
 */
final readonly class ChineseNewYearRule implements WithinTheYear
{
    #[Override]
    public function in(int $year, DateTimeZone $zone): array
    {
        $day = ChineseNewYear::of($year);

        return [Days::of($year, (int) $day->format('n'), (int) $day->format('j'), $zone)];
    }

    #[Override]
    public function isInstant(): bool
    {
        return false;
    }

    #[Override]
    public function __toString(): string
    {
        return 'chinese new year';
    }
}
