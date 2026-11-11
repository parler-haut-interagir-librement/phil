<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Infrastructure\Intl;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Locale;
use Phil\OpeningHours\DayOfWeek;
use Phil\OpeningHours\TimeOfDay;

/**
 * Days, dates and times written by ICU in a language: `lundi`, `25 déc. 2026`, `9 h 00`, `9:00 AM`. Each formatter
 * is built once per language and style, then kept for the rest of the process: a page that lists many places writes
 * its days and times with a few formatters, not one per text.
 */
final class Intl
{
    /** The languages whose formatters are kept at most: past it, they are built again, so that languages taken from requests cannot grow the memory without end. */
    private const int KEPT = 64;

    /**
     * The formatters by language, then by date style and time style: each style pair has one pattern at most.
     *
     * @var array<string, array<int, array<int, IntlDateFormatter>>>
     */
    private static array $formatters = [];

    public static function day(DayOfWeek $day, ?string $locale): string
    {
        // 2024 starts on a Monday.
        return self::text(self::formatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'EEEE'), new DateTimeImmutable('2024-01-0'.$day->value, new DateTimeZone('UTC')));
    }

    /**
     * @param string $date `Y-m-d`
     */
    public static function date(string $date, ?string $locale): string
    {
        return self::text(self::formatter($locale, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE), new DateTimeImmutable($date, new DateTimeZone('UTC')));
    }

    /**
     * The end of the day is written as midnight.
     */
    public static function time(TimeOfDay $time, ?string $locale): string
    {
        return self::text(self::formatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::SHORT), $time->minutes % TimeOfDay::END_OF_DAY * 60);
    }

    /**
     * A formatter in UTC, kept by the language it was asked for — the default one when it is `null`, read each time, so
     * that a change of default language gets formatters of its own.
     */
    private static function formatter(?string $locale, int $date, int $time, ?string $pattern = null): IntlDateFormatter
    {
        $locale ??= Locale::getDefault();

        if (!isset(self::$formatters[$locale]) && \count(self::$formatters) >= self::KEPT) {
            self::$formatters = [];
        }

        return self::$formatters[$locale][$date][$time] ??= new IntlDateFormatter($locale, $date, $time, 'UTC', null, $pattern);
    }

    private static function text(IntlDateFormatter $formatter, DateTimeImmutable|int $value): string
    {
        $text = $formatter->format($value);

        return false === $text ? '' : $text;
    }
}
