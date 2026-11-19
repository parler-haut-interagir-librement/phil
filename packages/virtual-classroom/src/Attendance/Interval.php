<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Attendance;

use DateTimeImmutable;

/**
 * One stay of a participant in a room: when they arrived and when they left, or null while they are still in it.
 */
final readonly class Interval
{
    public function __construct(
        public DateTimeImmutable $joinedAt,
        public ?DateTimeImmutable $leftAt = null,
    ) {
    }
}
