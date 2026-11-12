<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeZone;
use Override;

/**
 * The same day every year — `12-25` —; February 29 only in leap years.
 */
final readonly class FixedDate implements WithinTheYear
{
    public function __construct(
        public int $month,
        public int $day,
    ) {
        Month::check($month, $day);
    }

    #[Override]
    public function in(int $year, DateTimeZone $zone): array
    {
        return checkdate($this->month, $this->day, $year) ? [Days::of($year, $this->month, $this->day, $zone)] : [];
    }

    #[Override]
    public function isInstant(): bool
    {
        return false;
    }

    #[Override]
    public function __toString(): string
    {
        return \sprintf('%02d-%02d', $this->month, $this->day);
    }
}
