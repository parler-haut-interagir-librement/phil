<?php

declare(strict_types=1);

namespace Phil\Citations\Internal;

use Phil\Citations\Exception\InvalidReference;
use Phil\Citations\Model\CslDate;
use Phil\Citations\Model\PartialDate;

/**
 * A date written as text, as CSL-JSON's `raw`, BibLaTeX's `date` and RIS write it: `2020`, `2020-05`, `2020-05-01`,
 * with `-` or `/` between the parts, a range of two such dates with `/` between them when the parts use `-`. Any
 * other text, and a date that does not exist, is kept as a literal date.
 *
 * @internal
 */
final class DateText
{
    public static function parse(string $text, bool $circa = false): CslDate
    {
        $text = trim($text);

        try {
            return self::calendar($text, $circa);
        } catch (InvalidReference) {
            return new CslDate(null, null, $circa, $text);
        }
    }

    private static function calendar(string $text, bool $circa): CslDate
    {
        if (1 === preg_match('#^(-?\d{1,4})(?:/(\d{1,2}))?(?:/(\d{1,2}))?/*$#D', $text, $matches)) {
            return new CslDate(self::date($matches), null, $circa);
        }

        $dates = [];

        foreach (explode('/', $text, 2) as $part) {
            if (1 !== preg_match('/^(-?\d{1,4})(?:-(\d{1,2}))?(?:-(\d{1,2}))?$/D', trim($part), $matches)) {
                return new CslDate(null, null, $circa, $text);
            }

            $dates[] = self::date($matches);
        }

        return new CslDate($dates[0], $dates[1] ?? null, $circa);
    }

    /**
     * @param array<int, string> $matches
     */
    private static function date(array $matches): PartialDate
    {
        $month = isset($matches[2]) ? (int) $matches[2] : null;
        $day = isset($matches[3]) ? (int) $matches[3] : null;

        return new PartialDate((int) $matches[1], $month, $day);
    }
}
