<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use Phil\Almanac\Exception\InvalidRule;

/**
 * Months by their English names, as rules write them.
 *
 * @internal
 */
final class Month
{
    public const array NAMES = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];

    public static function name(int $month): string
    {
        return self::NAMES[$month - 1] ?? throw InvalidRule::because(\sprintf('A month is 1 to 12, not %d.', $month));
    }

    public static function check(int $month, int $day): void
    {
        // 2000 is a leap year: February 29 is a day of the year.
        if (!checkdate($month, $day, 2000)) {
            throw InvalidRule::because(\sprintf('%02d-%02d is no day of the year.', $month, $day));
        }
    }
}
