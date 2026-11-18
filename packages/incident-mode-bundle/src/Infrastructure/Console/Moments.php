<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Console;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The moments of the commands, in UTC, so that two operators in different time zones read the same end.
 */
final class Moments
{
    private function __construct()
    {
    }

    public static function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i');
    }
}
