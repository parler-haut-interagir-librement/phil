<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Moments in UTC, compared by their order: two dates are never compared with `==` or `===`.
 *
 * @internal
 */
final class Moment
{
    public static function utc(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone(new DateTimeZone('UTC'));
    }

    public static function same(DateTimeImmutable $first, DateTimeImmutable $second): bool
    {
        return 0 === ($first <=> $second);
    }

    /**
     * Whether the moment lies in the half-open range from `$from`, included, to `$to`, excluded.
     */
    public static function within(DateTimeImmutable $moment, DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return $moment >= $from && $moment < $to;
    }
}
