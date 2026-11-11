<?php

declare(strict_types=1);

namespace Phil\OpeningHours;

use Phil\OpeningHours\Exception\InvalidOpeningHours;

/**
 * Hours as people type them, and back.
 *
 * A day is its ranges separated by commas, `09:00-12:00, 13:00-17:00`, or nothing when it is closed. Special hours
 * are one period a line — a day or `from..through`, its ranges, and a label after `#`:
 *
 *     2026-12-24 09:00-12:00 # Christmas Eve
 *     2026-12-25 # Christmas
 *     2027-07-01..2027-08-31 10:00-16:00
 */
final class HoursText
{
    public static function day(string $text): DayHours
    {
        $text = trim($text);

        return '' === $text ? DayHours::closed() : DayHours::of(array_map(TimeRange::parse(...), explode(',', $text)));
    }

    /**
     * @return list<SpecialHours>
     */
    public static function specials(string $text): array
    {
        $specials = [];

        foreach (explode("\n", str_replace("\r", "\n", $text)) as $line) {
            $parts = explode('#', $line, 2);
            $period = trim($parts[0]);
            $label = $parts[1] ?? null;

            if ('' === $period) {
                continue;
            }

            if (1 !== preg_match('/^(?<from>\d{4}-\d{2}-\d{2})(?:\.\.(?<through>\d{4}-\d{2}-\d{2}))?(?:\s+(?<ranges>.+))?$/', $period, $match)) {
                throw InvalidOpeningHours::unreadable($line, 'a line of special hours');
            }

            $specials[] = SpecialHours::of($match['from'], '' === ($match['through'] ?? '') ? null : $match['through'], self::day($match['ranges'] ?? ''), $label);
        }

        return $specials;
    }

    /**
     * @param list<SpecialHours> $specials
     */
    public static function specialsToText(array $specials): string
    {
        return implode("\n", array_map(static fn (SpecialHours $special): string => implode(' ', array_filter([
            $special->from.($special->through === $special->from ? '' : '..'.$special->through),
            $special->hours->toText(),
            null === $special->label ? '' : '# '.$special->label,
        ], static fn (string $part): bool => '' !== $part)), $specials));
    }
}
