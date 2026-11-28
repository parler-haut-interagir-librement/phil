<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Application\Contract;

use DateTimeImmutable;

/**
 * The birth dates the application knows of its volunteers, read where it keeps them: the bundle stores none. Only the
 * calendar date counts, its time and time zone are ignored.
 */
interface BirthDates
{
    /**
     * The birth date of the volunteer, or null when the application does not know it.
     */
    public function of(string $volunteer): ?DateTimeImmutable;
}
