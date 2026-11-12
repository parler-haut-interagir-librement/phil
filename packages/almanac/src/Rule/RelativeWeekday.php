<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeZone;
use Override;
use Phil\Recurrence\Weekday;

/**
 * A weekday found from a day: `saturday on or after 09-15`, `monday before 05-25`.
 */
final readonly class RelativeWeekday implements WithinTheYear
{
    public function __construct(
        public Weekday $weekday,
        public Relation $relation,
        public int $month,
        public int $day,
    ) {
        Month::check($month, $day);
    }

    #[Override]
    public function in(int $year, DateTimeZone $zone): array
    {
        if (!checkdate($this->month, $this->day, $year)) {
            return [];
        }

        $from = Days::of($year, $this->month, $this->day, $zone);
        $forward = ($this->weekday->iso() - Weekday::of($from)->iso() + 7) % 7;
        $backward = (Weekday::of($from)->iso() - $this->weekday->iso() + 7) % 7;

        return [$from->modify(match ($this->relation) {
            Relation::OnOrAfter => \sprintf('+%d days', $forward),
            Relation::After => \sprintf('+%d days', 0 === $forward ? 7 : $forward),
            Relation::OnOrBefore => \sprintf('-%d days', $backward),
            Relation::Before => \sprintf('-%d days', 0 === $backward ? 7 : $backward),
        })];
    }

    #[Override]
    public function isInstant(): bool
    {
        return false;
    }

    #[Override]
    public function __toString(): string
    {
        return \sprintf('%s %s %02d-%02d', strtolower($this->weekday->name), $this->relation->value, $this->month, $this->day);
    }
}
