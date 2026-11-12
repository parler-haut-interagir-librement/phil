<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeImmutable;
use DateTimeZone;
use Stringable;

/**
 * A way to find dates in a year: a fixed day, the nth weekday of a month, Easter, a phase of the Moon….
 *
 * Its text, `__toString()`, is what `RuleParser` reads back: a rule can be kept in configuration or in a column.
 */
interface Rule extends Stringable
{
    /**
     * Its dates in a year, in order: midnights in the time zone for a rule of days, instants for a rule of the sky.
     *
     * @return list<DateTimeImmutable>
     */
    public function in(int $year, DateTimeZone $zone): array;

    /**
     * Whether it finds instants — a phase of the Moon, a solstice — rather than days.
     */
    public function isInstant(): bool;
}
