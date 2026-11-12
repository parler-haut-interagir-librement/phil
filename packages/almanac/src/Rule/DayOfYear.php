<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeZone;
use Override;
use Phil\Almanac\Exception\InvalidRule;

/**
 * The nth day of the year — `day 256` —; day 366 only in leap years.
 */
final readonly class DayOfYear implements WithinTheYear
{
    public function __construct(
        public int $day,
    ) {
        if ($day < 1 || $day > 366) {
            throw InvalidRule::because(\sprintf('A day of the year is 1 to 366, not %d.', $day));
        }
    }

    #[Override]
    public function in(int $year, DateTimeZone $zone): array
    {
        $day = Days::of($year, 1, 1, $zone)->modify(\sprintf('+%d days', $this->day - 1));

        return (int) $day->format('Y') === $year ? [$day] : [];
    }

    #[Override]
    public function isInstant(): bool
    {
        return false;
    }

    #[Override]
    public function __toString(): string
    {
        return 'day '.$this->day;
    }
}
