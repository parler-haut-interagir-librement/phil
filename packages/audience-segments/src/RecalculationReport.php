<?php

declare(strict_types=1);

namespace Phil\AudienceSegments;

use DateTimeImmutable;

/**
 * What a recalculation found: how many members the segment holds now, how many entered and how many left, and when.
 */
final readonly class RecalculationReport
{
    public function __construct(
        public string $segment,
        public int $members,
        public int $entered,
        public int $left,
        public DateTimeImmutable $at,
    ) {
    }
}
