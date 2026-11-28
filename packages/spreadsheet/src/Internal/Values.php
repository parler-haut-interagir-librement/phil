<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Internal;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Spreadsheet\Cell;

/**
 * The text forms of numbers and dates shared by the formats, and the parsing of a number's text.
 *
 * @internal
 */
final class Values
{
    /**
     * A number as text, independent of the locale: the shortest text that reads back as the same float.
     */
    public static function number(int|float $number): string
    {
        return (string) $number;
    }

    /**
     * A date as ISO 8601: the day, or the day and the time with a separator, its fraction of a second only when it has
     * one.
     */
    public static function date(DateTimeImmutable $date, ?string $timeSeparator = null): string
    {
        if (null === $timeSeparator) {
            return $date->format('Y-m-d');
        }

        $text = $date->format('Y-m-d').$timeSeparator.$date->format('H:i:s');
        $micro = $date->format('u');

        return '000000' === $micro ? $text : $text.'.'.rtrim($micro, '0');
    }

    /**
     * The number a text writes, or null when it writes none: digits with a sign, a decimal point and an exponent, no
     * space. An integer that fits is an integer.
     */
    public static function parseNumber(string $text): int|float|null
    {
        if (1 !== preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/D', $text)) {
            return null;
        }

        if (1 === preg_match('/^[+-]?\d{1,18}$/D', $text)) {
            return (int) $text;
        }

        $number = (float) $text;

        return is_finite($number) ? $number : null;
    }

    /**
     * The cell of an ISO 8601 date — `2026-11-21` is a date, `2026-11-21T14:30:00`, with a fraction of a second or an
     * offset, a date and time — or null when the text is not one, or names a day that does not exist.
     */
    public static function isoDate(string $text): ?Cell
    {
        if (1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2})(?:[.,](\d{1,9}))?)?(Z|[+-]\d{2}:?\d{2})?)?$/D', $text, $match, \PREG_UNMATCHED_AS_NULL)) {
            return null;
        }

        [, $year, $month, $day] = $match;

        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            return null;
        }

        $utc = new DateTimeZone('UTC');

        if (null === $match[4]) {
            return Cell::date(new DateTimeImmutable($year.'-'.$month.'-'.$day, $utc));
        }

        if ((int) $match[4] > 23 || (int) $match[5] > 59 || (int) ($match[6] ?? 0) > 59) {
            return null;
        }

        $zone = null === $match[8] ? $utc : new DateTimeZone('Z' === $match[8] ? 'UTC' : $match[8]);
        $micro = substr(str_pad($match[7] ?? '', 6, '0'), 0, 6);

        return Cell::dateTime(new DateTimeImmutable(\sprintf('%s-%s-%sT%s:%s:%s.%s', $year, $month, $day, $match[4], $match[5], $match[6] ?? '00', $micro), $zone));
    }

    /**
     * The wall-clock time of a date, read as UTC: spreadsheets have no time zones.
     */
    public static function wallClock(DateTimeImmutable $date): DateTimeImmutable
    {
        [$year, $month, $day, $hour, $minute, $second, $micro] = array_map(intval(...), explode(' ', $date->format('Y n j G i s u')));

        return new DateTimeImmutable('@0')->setDate($year, $month, $day)->setTime($hour, $minute, $second, $micro);
    }
}
