<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Event;

/**
 * A responsible person stepped aside from a report. It names the report and the person only.
 */
final readonly class ReportRecused
{
    public function __construct(
        public string $report,
        public string $person,
    ) {
    }
}
