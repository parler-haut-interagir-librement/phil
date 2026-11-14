<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Chain;

use DateTimeImmutable;

/**
 * The boundary hashes of a segment at the end of a period, as written outside the database.
 */
final readonly class Anchor
{
    public function __construct(
        public string $segment,
        public DateTimeImmutable $periodEnd,
        public string $firstHash,
        public string $headHash,
    ) {
    }
}
