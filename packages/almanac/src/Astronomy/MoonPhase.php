<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

use DateTimeImmutable;

/**
 * One phase of the Moon and its instant, in UTC.
 */
final readonly class MoonPhase
{
    public function __construct(
        public Phase $phase,
        public DateTimeImmutable $at,
    ) {
    }
}
