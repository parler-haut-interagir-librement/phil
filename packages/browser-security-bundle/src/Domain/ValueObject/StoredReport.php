<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\ValueObject;

use DateTimeImmutable;

/**
 * A violation as a store keeps it: the report, the times it came, the first and last time, and the anonymized address
 * of the last client, when the application keeps one.
 */
final readonly class StoredReport
{
    public function __construct(
        public ViolationReport $report,
        public int $count,
        public DateTimeImmutable $firstSeenAt,
        public DateTimeImmutable $lastSeenAt,
        public ?string $client = null,
    ) {
    }
}
