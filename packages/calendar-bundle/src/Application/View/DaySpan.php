<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use DateTimeImmutable;
use IntlDateFormatter;
use IntlDatePatternGenerator;

/**
 * The days a group covers, in words — the heading of a list:
 *
 * | Days | French | English |
 * |---|---|---|
 * | one | mardi 1er septembre | Tuesday, September 1 |
 * | two | 1er et 2 septembre | September 1 and 2 |
 * | three or more | 2 au 4 septembre | September 2–4 |
 * | across months | 30 août au 2 septembre, 31 août et 1er septembre | August 30 – September 2, August 31 and September 1 |
 *
 * The year is written when the days are not all in the year of reference — that of the period shown —, after the
 * last day, and after the first too when the two are in different years. Another
 * language writes each day as ICU's patterns for it do — *1. September – 2. September* —, joined by a dash.
 */
final class DaySpan
{
    public static function label(DateTimeImmutable $first, DateTimeImmutable $last, string $locale, ?int $year = null): string
    {
        $language = strtolower(substr($locale, 0, 2));
        $year ??= (int) $first->format('Y');
        $withYear = (int) $first->format('Y') !== $year || (int) $last->format('Y') !== $year;
        $days = $first->diff($last)->days + 1;
        $sameMonth = $first->format('Y-m') === $last->format('Y-m');

        if (1 === $days) {
            return match ($language) {
                'fr' => self::format($first, $locale, 'EEEE').' '.self::french($first, $withYear),
                default => self::format($first, $locale, self::skeleton($locale, $withYear ? 'yMMMMEEEEd' : 'MMMMEEEEd')),
            };
        }

        $yearsDiffer = $first->format('Y') !== $last->format('Y');

        return match ($language) {
            'fr' => ($sameMonth ? self::frenchDay($first) : self::french($first, $yearsDiffer)).(2 === $days ? ' et ' : ' au ').self::french($last, $withYear),
            'en' => self::english($first, $last, $locale, $days, $sameMonth, $withYear, $yearsDiffer),
            default => self::format($first, $locale, self::skeleton($locale, $withYear ? 'yMMMMd' : 'MMMMd')).' – '.self::format($last, $locale, self::skeleton($locale, $withYear ? 'yMMMMd' : 'MMMMd')),
        };
    }

    /**
     * Days in English, the month first where the locale writes it so — September 1 and 2 —, else the day first —
     * 1 and 2 September.
     */
    private static function english(DateTimeImmutable $first, DateTimeImmutable $last, string $locale, int $days, bool $sameMonth, bool $withYear, bool $yearsDiffer): string
    {
        $and = 2 === $days ? ' and ' : ($sameMonth ? '–' : ' – ');

        if (str_starts_with(self::skeleton($locale, 'MMMMd'), 'd')) {
            $year = $withYear ? ' '.$last->format('Y') : '';

            return $sameMonth
                ? $first->format('j').$and.self::format($last, $locale, 'd MMMM').$year
                : self::format($first, $locale, $yearsDiffer ? 'd MMMM y' : 'd MMMM').$and.self::format($last, $locale, 'd MMMM').$year;
        }

        $year = $withYear ? ', '.$last->format('Y') : '';

        return $sameMonth
            ? self::format($first, $locale, 'MMMM d').$and.$last->format('j').$year
            : self::format($first, $locale, $yearsDiffer ? 'MMMM d, y' : 'MMMM d').$and.self::format($last, $locale, 'MMMM d').$year;
    }

    /**
     * A day in French: 1er for the first of a month.
     */
    private static function french(DateTimeImmutable $date, bool $withYear): string
    {
        return self::frenchDay($date).' '.self::format($date, 'fr', 'MMMM').($withYear ? ' '.$date->format('Y') : '');
    }

    private static function frenchDay(DateTimeImmutable $date): string
    {
        return '1' === $date->format('j') ? '1er' : $date->format('j');
    }

    /**
     * The pattern ICU writes a skeleton with in a language: `d. MMMM` for `MMMMd` in German.
     */
    private static function skeleton(string $locale, string $skeleton): string
    {
        return (string) new IntlDatePatternGenerator($locale)->getBestPattern($skeleton);
    }

    private static function format(DateTimeImmutable $date, string $locale, string $pattern): string
    {
        $text = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $date->getTimezone(), null, $pattern)->format($date);

        return false === $text ? '' : $text;
    }
}
