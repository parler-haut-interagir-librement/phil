<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Event;

/**
 * A report job was exported, and delivered when it named a delivery: the identifiers of the job, now forgotten, and of
 * its file, kept in the export store unless it was delivered.
 */
final readonly class ReportExported
{
    public function __construct(
        public string $job,
        public string $export,
        public bool $delivered,
    ) {
    }
}
