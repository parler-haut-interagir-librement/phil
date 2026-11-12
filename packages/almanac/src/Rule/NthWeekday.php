<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeZone;
use Override;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Recurrence\Weekday;

/**
 * The nth weekday of a month — `2nd monday of october` —, or counted from its end — `last thursday of february`.
 * A fifth weekday a month does not have gives nothing that year.
 */
final readonly class NthWeekday implements WithinTheYear
{
    private const array ORDINALS = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th', -1 => 'last'];

    /**
     * @param int $nth 1 to 5, or -1 for the last
     */
    public function __construct(
        public int $nth,
        public Weekday $weekday,
        public int $month,
    ) {
        if (!isset(self::ORDINALS[$nth])) {
            throw InvalidRule::because(\sprintf('A weekday is the 1st to the 5th of its month, or the last; not %d.', $nth));
        }

        Month::name($month);
    }

    #[Override]
    public function in(int $year, DateTimeZone $zone): array
    {
        $first = Days::of($year, $this->month, 1, $zone);

        if (-1 === $this->nth) {
            $last = $first->modify('last day of this month');
            $back = (Weekday::of($last)->iso() - $this->weekday->iso() + 7) % 7;

            return [$last->modify(\sprintf('-%d days', $back))];
        }

        $day = 1 + ($this->weekday->iso() - Weekday::of($first)->iso() + 7) % 7 + 7 * ($this->nth - 1);

        return checkdate($this->month, $day, $year) ? [Days::of($year, $this->month, $day, $zone)] : [];
    }

    #[Override]
    public function isInstant(): bool
    {
        return false;
    }

    #[Override]
    public function __toString(): string
    {
        return \sprintf('%s %s of %s', self::ORDINALS[$this->nth], strtolower($this->weekday->name), Month::name($this->month));
    }
}
