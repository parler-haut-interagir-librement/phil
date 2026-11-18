<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Application;

use DateTimeImmutable;

/**
 * One restriction that applies to the member now, and the moment it stops applying: the latest end of the incidents
 * in force that suspend it. `read-only` stands for everything but reading.
 */
final readonly class Suspension
{
    public function __construct(
        public string $restriction,
        public DateTimeImmutable $until,
    ) {
    }

    /**
     * The seconds from the moment given to the end, at least one: the value of a `Retry-After` header.
     *
     * @return positive-int
     */
    public function secondsFrom(DateTimeImmutable $now): int
    {
        return max(1, $this->until->getTimestamp() - $now->getTimestamp());
    }
}
