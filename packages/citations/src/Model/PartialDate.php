<?php

declare(strict_types=1);

namespace Phil\Citations\Model;

use Phil\Citations\Exception\InvalidReference;

/**
 * A date that may stop at its year or its month, as the dates of documents often do: 2020, May 2020, 1 May 2020, or a
 * season of a year.
 */
final readonly class PartialDate
{
    /**
     * @param int      $year   from -9999 to 9999, negative before the common era, never 0
     * @param int|null $month  from 1 to 12
     * @param int|null $day    a day of that month; needs the month
     * @param int|null $season from 1 (spring) to 4 (winter), for a year without a month
     *
     * @throws InvalidReference a date that does not exist
     */
    public function __construct(
        public int $year,
        public ?int $month = null,
        public ?int $day = null,
        public ?int $season = null,
    ) {
        if (0 === $year || abs($year) > 9999) {
            throw InvalidReference::because(\sprintf('the year %d is outside -9999 to 9999 or is 0', $year));
        }

        if (null !== $month && ($month < 1 || $month > 12)) {
            throw InvalidReference::because(\sprintf('the month %d is outside 1 to 12', $month));
        }

        if (null !== $day && (null === $month || !checkdate($month, $day, $year > 0 ? $year : 2000))) {
            throw InvalidReference::because(\sprintf('the day %d does not exist in that month', $day));
        }

        if (null !== $season && (null !== $month || $season < 1 || $season > 4)) {
            throw InvalidReference::because('a season is 1 to 4, for a year without a month');
        }
    }

    /**
     * The parts as CSL-JSON lists them: year, then month and day when known.
     *
     * @return list<int>
     */
    public function parts(): array
    {
        $parts = [$this->year];

        if (null !== $this->month) {
            $parts[] = $this->month;

            if (null !== $this->day) {
                $parts[] = $this->day;
            }
        }

        return $parts;
    }
}
