<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Domain;

use DateTimeImmutable;

/**
 * The seconds between two moments, to the microsecond, never negative.
 */
final class Elapsed
{
    public static function seconds(DateTimeImmutable $from, DateTimeImmutable $to): float
    {
        $seconds = $to->getTimestamp() - $from->getTimestamp();
        $micro = $to->getMicrosecond() - $from->getMicrosecond();

        return max(0.0, $seconds + $micro / 1_000_000);
    }
}
