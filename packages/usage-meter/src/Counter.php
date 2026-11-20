<?php

declare(strict_types=1);

namespace Phil\UsageMeter;

use DateTimeImmutable;

/**
 * One counter of a store: a subject's count of a meter from one start — a period, or a bucket of a rolling window —,
 * and when it stops counting in any window.
 */
final readonly class Counter
{
    public function __construct(
        public string $subject,
        public string $meter,
        public DateTimeImmutable $start,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
