<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application;

use DateTimeImmutable;

/**
 * A date a new scheduling poll proposes: its start and its end, excluded, in any time zone.
 */
final readonly class ProposedSlot
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {
    }
}
