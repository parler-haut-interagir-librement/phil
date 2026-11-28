<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

use DateTimeImmutable;

/**
 * What the person who filed a report sees of it: that it was received, where it stands, since when. Never the notes,
 * the outcome, or who handles it.
 */
final readonly class ReportReceipt
{
    public function __construct(
        public string $id,
        public ReportStatus $status,
        public DateTimeImmutable $filedAt,
        public ?DateTimeImmutable $updatedAt,
    ) {
    }
}
