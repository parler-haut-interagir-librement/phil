<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use Phil\Almanac\Astronomy\LunarEclipseKind;
use Phil\Almanac\Astronomy\Phase;
use Phil\Almanac\Astronomy\Season;
use Phil\Almanac\Astronomy\SolarEclipseKind;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Recurrence\Weekday;

/**
 * Reads a rule from its text, in English, in any case and spacing:
 *
 * | Text | Rule |
 * |---|---|
 * | `12-25` | `FixedDate` |
 * | `2nd monday of october`, `last thursday of february`, `first saturday of 5` | `NthWeekday` |
 * | `saturday on or after 09-15`, `monday before 05-25` | `RelativeWeekday` |
 * | `easter`, `orthodox easter` | `EasterSunday` |
 * | `day 256` | `DayOfYear` |
 * | `friday 13` | `WeekdayOnMonthDay` |
 * | `new moon`, `first quarter`, `full moon`, `last quarter` | `MoonPhaseRule` |
 * | `march equinox`, `june solstice`, `september equinox`, `december solstice` | `SeasonRule` |
 * | `chinese new year` | `ChineseNewYearRule` |
 * | `solar eclipse`, `total solar eclipse`, `lunar eclipse`, `penumbral lunar eclipse`… | `EclipseRule` |
 * | any of them, then `+ 3 days` or `- 47 days` | `Offset` |
 *
 * A rule's `__toString()` writes the canonical text, which this reads back to the same rule.
 */
final class RuleParser
{
    private const array ORDINALS = ['1st' => 1, 'first' => 1, '2nd' => 2, 'second' => 2, '3rd' => 3, 'third' => 3, '4th' => 4, 'fourth' => 4, '5th' => 5, 'fifth' => 5, 'last' => -1];

    private const string WEEKDAY = '(?<weekday>monday|tuesday|wednesday|thursday|friday|saturday|sunday)';

    private const string DAY = '(?<month>\d{1,2})-(?<day>\d{1,2})';

    public static function parse(string $text): Rule
    {
        $normal = strtolower(trim((string) preg_replace('/\s+/', ' ', $text)));

        // The text has no new line left: `$` needs no `D`.
        if (1 === preg_match('/(?<rule>.+?) ?(?<sign>[+-]) ?(?<days>\d{1,3}) days?$/', $normal, $offset)) {
            $days = (int) $offset['days'];

            return new Offset(self::base($offset['rule'], $text), '-' === $offset['sign'] ? -$days : $days);
        }

        return self::base($normal, $text);
    }

    private static function base(string $normal, string $text): Rule
    {
        return match (true) {
            1 === preg_match('/^'.self::DAY.'$/', $normal, $parts) => new FixedDate((int) $parts['month'], (int) $parts['day']),
            1 === preg_match('/^(?<nth>\S+) '.self::WEEKDAY.' of (?<month>\S+)$/', $normal, $parts) => new NthWeekday(self::ORDINALS[$parts['nth']] ?? throw InvalidRule::unreadable($text), self::weekday($parts['weekday']), self::month($parts['month'], $text)),
            1 === preg_match('/^'.self::WEEKDAY.' (?<relation>on or after|after|on or before|before) '.self::DAY.'$/', $normal, $parts) => new RelativeWeekday(self::weekday($parts['weekday']), Relation::from($parts['relation']), (int) $parts['month'], (int) $parts['day']),
            'easter' === $normal => new EasterSunday(),
            'orthodox easter' === $normal => new EasterSunday(orthodox: true),
            1 === preg_match('/^day (?<day>\d{1,3})$/', $normal, $parts) => new DayOfYear((int) $parts['day']),
            1 === preg_match('/^'.self::WEEKDAY.' (?<day>\d{1,2})$/', $normal, $parts) => new WeekdayOnMonthDay(self::weekday($parts['weekday']), (int) $parts['day']),
            null !== Phase::tryFrom($normal) => new MoonPhaseRule(Phase::from($normal)),
            null !== Season::tryFrom($normal) => new SeasonRule(Season::from($normal)),
            'chinese new year' === $normal => new ChineseNewYearRule(),
            1 === preg_match('/^(?:(?<kind>total|annular|hybrid|partial) )?solar eclipse$/', $normal, $parts) => EclipseRule::solar(SolarEclipseKind::tryFrom($parts['kind'] ?? '')),
            1 === preg_match('/^(?:(?<kind>total|partial|penumbral) )?lunar eclipse$/', $normal, $parts) => EclipseRule::lunar(LunarEclipseKind::tryFrom($parts['kind'] ?? '')),
            default => throw InvalidRule::unreadable($text),
        };
    }

    private static function weekday(string $name): Weekday
    {
        foreach (Weekday::cases() as $weekday) {
            if (strtolower($weekday->name) === $name) {
                return $weekday;
            }
        }

        throw InvalidRule::because(\sprintf('"%s" is no weekday.', $name));
    }

    private static function month(string $month, string $text): int
    {
        if (1 === preg_match('/^\d{1,2}$/', $month)) {
            return (int) $month;
        }

        $index = array_search($month, Month::NAMES, true);

        return false === $index ? throw InvalidRule::unreadable($text) : $index + 1;
    }
}
