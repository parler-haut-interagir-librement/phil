<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Application;

use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Exception\InvalidIncident;

/**
 * A duration as a person writes it, on the command line or in the administration: `90` (minutes), `45m`, `2h`,
 * `3 days`, `1d 12h`. The parts add up; the bounds of `Duration` — one minute to ninety days — apply to the sum.
 *
 * The text is at most 64 characters, each number at most six digits and at most eight parts, so that reading it is
 * bounded whatever it holds.
 */
final readonly class DurationInput
{
    public const int MAX_LENGTH = 64;

    public const string MINUTES = 'minutes';

    public const string HOURS = 'hours';

    public const string DAYS = 'days';

    /** The units of the administration's form, from the shortest. */
    public const array UNITS = [self::MINUTES => 60, self::HOURS => 3_600, self::DAYS => 86_400];

    private const string PART = '(\d{1,6})\s*(days?|d|hours?|h|minutes?|mins?|m)';

    private function __construct()
    {
    }

    /**
     * @throws InvalidIncident when the text is no duration, or the duration is out of bounds
     */
    public static function parse(string $text): Duration
    {
        $text = strtolower(trim($text));

        if (\strlen($text) > self::MAX_LENGTH) {
            throw InvalidIncident::because(\sprintf('A duration is written in at most %d characters.', self::MAX_LENGTH));
        }

        if (1 === preg_match('/\A\d{1,6}\z/', $text)) {
            return Duration::ofMinutes((int) $text);
        }

        if (1 !== preg_match('/^'.self::PART.'(?:\s*'.self::PART.'){0,7}$/D', $text)) {
            throw InvalidIncident::because(\sprintf('"%s" is not a duration such as 90, 45m, 2h, 3d or 1d 12h.', $text));
        }

        preg_match_all('/'.self::PART.'/', $text, $parts, \PREG_SET_ORDER);
        $seconds = 0;

        foreach ($parts as [, $number, $unit]) {
            $seconds += (int) $number * match ($unit[0]) {
                'd' => self::UNITS[self::DAYS],
                'h' => self::UNITS[self::HOURS],
                default => self::UNITS[self::MINUTES],
            };
        }

        return Duration::ofSeconds($seconds);
    }

    /**
     * The duration of an amount of one of the form's units.
     *
     * @throws InvalidIncident when the unit is unknown, or the duration is out of bounds
     */
    public static function ofAmount(int $amount, string $unit): Duration
    {
        $seconds = self::UNITS[$unit] ?? throw InvalidIncident::because(\sprintf('"%s" is not a unit of duration: minutes, hours or days.', $unit));

        return Duration::ofSeconds($amount > intdiv(Duration::MAXIMUM, $seconds) ? Duration::MAXIMUM + 1 : $amount * $seconds);
    }

    /**
     * The duration in the largest unit that counts it whole: two days, 36 hours, 90 minutes.
     *
     * @return array{amount: int, unit: self::MINUTES|self::HOURS|self::DAYS}
     */
    public static function split(Duration $duration): array
    {
        foreach ([self::DAYS, self::HOURS] as $unit) {
            if (0 === $duration->seconds % self::UNITS[$unit]) {
                return ['amount' => intdiv($duration->seconds, self::UNITS[$unit]), 'unit' => $unit];
            }
        }

        return ['amount' => intdiv($duration->seconds, self::UNITS[self::MINUTES]), 'unit' => self::MINUTES];
    }

    /**
     * The duration as `parse()` reads it back: `2d`, `36h`, `90m`.
     */
    public static function format(Duration $duration): string
    {
        $split = self::split($duration);

        return $split['amount'].$split['unit'][0];
    }
}
