<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * The clock's time in UTC, as the columns keep it.
 *
 * @internal
 */
final class Utc
{
    public static function now(ClockInterface $clock): DateTimeImmutable
    {
        return $clock->now()->setTimezone(new DateTimeZone('UTC'));
    }
}
