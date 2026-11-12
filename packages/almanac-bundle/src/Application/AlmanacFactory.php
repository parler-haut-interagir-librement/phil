<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Application;

use DateTimeZone;
use Phil\Almanac\Almanac;

/**
 * The almanac of the application, built from its definitions when it is asked for, so that a date stored a moment ago
 * is in it.
 */
final readonly class AlmanacFactory
{
    /** The service of the almanac it builds. */
    public const string ALMANAC = Almanac::class;

    public function __construct(
        private Definitions $definitions,
        private string $timezone,
    ) {
    }

    public function almanac(): Almanac
    {
        return new Almanac($this->definitions->all(), $this->zone());
    }

    /**
     * The time zone the dates are computed in.
     */
    public function zone(): DateTimeZone
    {
        return new DateTimeZone($this->timezone);
    }
}
