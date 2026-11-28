<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Event;

/**
 * A responsible person opened a report. It names the report and the reader only: the journal of who read what.
 */
final readonly class ReportViewed
{
    public function __construct(
        public string $report,
        public string $reader,
    ) {
    }
}
