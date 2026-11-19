<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * A moment as the servers write it, read in UTC: a Unix time in seconds, or in milliseconds from 10^11 on — the year
 * 5138 in seconds, March 1973 in milliseconds —, as a number or as digits; or a date and time of RFC 3339. Anything
 * else, or a moment before 2000 or after 2200, is no moment.
 *
 * @internal
 */
final readonly class Timestamps
{
    private const int FROM = 946_684_800;

    private const int UNTIL = 7_258_118_400;

    private function __construct()
    {
    }

    public static function read(mixed $value): ?DateTimeImmutable
    {
        if (\is_string($value) && 1 === preg_match('/^\d{1,16}$/D', $value)) {
            $value = (int) $value;
        }

        if (\is_float($value) && is_finite($value) && $value >= 0 && $value < 1e16) {
            $value = (int) $value;
        }

        if (\is_int($value)) {
            $seconds = $value >= 100_000_000_000 ? intdiv($value, 1000) : $value;

            return self::within(new DateTimeImmutable('@'.$seconds));
        }

        if (!\is_string($value) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?(?:[Zz]|[+-]\d{2}:\d{2})$/D', $value)) {
            return null;
        }

        try {
            return self::within(new DateTimeImmutable($value)->setTimezone(new DateTimeZone('UTC')));
        } catch (Exception) {
            return null;
        }
    }

    private static function within(DateTimeImmutable $moment): ?DateTimeImmutable
    {
        $seconds = $moment->getTimestamp();

        return $seconds < self::FROM || $seconds > self::UNTIL ? null : $moment;
    }
}
