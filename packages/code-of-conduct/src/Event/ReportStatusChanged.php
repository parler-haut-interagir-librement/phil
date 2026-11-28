<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Event;

use Phil\CodeOfConduct\Outcome;
use Phil\CodeOfConduct\ReportStatus;

/**
 * A report moved to another status, with its outcome once resolved. It names the report, who moved it, the status and the outcome only; a listener applies the outcome through the application's moderation.
 */
final readonly class ReportStatusChanged
{
    public function __construct(
        public string $report,
        public string $by,
        public ReportStatus $status,
        public ?Outcome $outcome,
    ) {
    }
}
