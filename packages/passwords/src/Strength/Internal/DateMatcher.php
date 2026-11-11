<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength\Internal;

use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;

/**
 * Finds the dates of a day, a month and a year in a password, with a separator — `14/03/1987`, `1987-3-14`,
 * `14.03.87` — or without — `140387`, `19870314` —, in any order of day, month and year an application writes, the
 * year written in two or four digits. Of the readings of the same digits, the one whose year is the nearest to the
 * reference year is kept. Also finds the years alone, from 1900 to 2099.
 *
 * Only parts of 4 to 10 characters are read: the work grows with the length of the password.
 *
 * @internal
 */
final readonly class DateMatcher
{
    private const int MIN_YEAR = 1000;

    private const int MAX_YEAR = 2099;

    /** A year of two digits above this one is read in the 1900s, otherwise in the 2000s. */
    private const int TWO_DIGIT_PIVOT = 50;

    /** The orders of a date with separators: which of the three parts is the year, the month and the day. */
    private const array ORDERS = [[0, 1, 2], [2, 1, 0], [2, 0, 1], [0, 2, 1]];

    private const string SEPARATED = '/^(\d{1,4})([\s\/\\\\_.-])(\d{1,2})\2(\d{1,4})$/';

    public function __construct(
        private int $referenceYear,
    ) {
    }

    /**
     * @param list<string> $characters
     *
     * @return list<PatternMatch>
     */
    public function matches(array $characters): array
    {
        $count = \count($characters);
        $matches = [];

        foreach (array_keys($characters) as $i) {
            $part = '';

            for ($j = $i; $j < min($count, $i + 10); ++$j) {
                $part .= $characters[$j];
                $length = $j - $i + 1;

                if ($length < 4) {
                    continue;
                }

                $date = ctype_digit($part) ? ($length <= 8 ? $this->unseparated($part) : null) : $this->separated($part);

                if (null !== $date) {
                    $matches[] = $this->match($i, $j, $part, ...$date);
                }

                if (4 === $length && ctype_digit($part) && \in_array(substr($part, 0, 2), ['19', '20'], true)) {
                    $year = (int) $part;
                    $matches[] = new PatternMatch(Pattern::Year, $i, $j, $part, $this->yearSpace($year), year: $year);
                }
            }
        }

        return $matches;
    }

    /**
     * @return ?array{int, int, int, string}
     */
    private function separated(string $part): ?array
    {
        if (1 !== preg_match(self::SEPARATED, $part, $groups)) {
            return null;
        }

        $date = $this->nearest([$groups[1], $groups[3], $groups[4]], self::ORDERS);

        return null === $date ? null : [...$date, $groups[2]];
    }

    /**
     * Every way of cutting the digits in three parts of one, two or four digits, in every order.
     *
     * @return ?array{int, int, int, string}
     */
    private function unseparated(string $digits): ?array
    {
        $length = \strlen($digits);
        $best = null;

        for ($first = 1; $first <= 4; ++$first) {
            for ($second = 1; $second <= 2; ++$second) {
                $third = $length - $first - $second;

                if ($third < 1 || $third > 4) {
                    continue;
                }

                $parts = [substr($digits, 0, $first), substr($digits, $first, $second), substr($digits, $first + $second)];
                $best = $this->nearer($best, $this->nearest($parts, self::ORDERS));
            }
        }

        return null === $best ? null : [...$best, ''];
    }

    /**
     * @param array{string, string, string} $parts
     * @param list<array{int, int, int}>    $orders
     *
     * @return ?array{int, int, int}
     */
    private function nearest(array $parts, array $orders): ?array
    {
        $best = null;

        foreach ($orders as [$year, $month, $day]) {
            $best = $this->nearer($best, $this->date($parts[$year], $parts[$month], $parts[$day]));
        }

        return $best;
    }

    /**
     * @param ?array{int, int, int} $best
     * @param ?array{int, int, int} $candidate
     *
     * @return ?array{int, int, int}
     */
    private function nearer(?array $best, ?array $candidate): ?array
    {
        if (null === $best || (null !== $candidate && abs($candidate[0] - $this->referenceYear) < abs($best[0] - $this->referenceYear))) {
            return $candidate;
        }

        return $best;
    }

    /**
     * @return ?array{int, int, int}
     */
    private function date(string $year, string $month, string $day): ?array
    {
        $yearLength = \strlen($year);

        if (3 === $yearLength || 1 === $yearLength || \strlen($month) > 2 || \strlen($day) > 2) {
            return null;
        }

        $value = (int) $year;

        if (2 === $yearLength) {
            $value += $value > self::TWO_DIGIT_PIVOT ? 1900 : 2000;
        }

        $monthValue = (int) $month;
        $dayValue = (int) $day;

        if ($value < self::MIN_YEAR || $value > self::MAX_YEAR || $monthValue < 1 || $monthValue > 12 || $dayValue < 1 || $dayValue > 31) {
            return null;
        }

        return [$value, $monthValue, $dayValue];
    }

    private function match(int $start, int $end, string $token, int $year, int $month, int $day, string $separator): PatternMatch
    {
        return new PatternMatch(
            Pattern::Date,
            $start,
            $end,
            $token,
            $this->yearSpace($year) * 365 * ('' === $separator ? 1 : 4),
            year: $year,
            month: $month,
            day: $day,
            separator: $separator,
        );
    }

    private function yearSpace(int $year): float
    {
        return (float) max(abs($year - $this->referenceYear), Guesses::MIN_YEAR_SPACE);
    }
}
