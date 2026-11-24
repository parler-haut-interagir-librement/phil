<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Contract;

use Phil\SeoCheck\Report;

/**
 * Writes a report in one format: text for a console or a log, JSON for an interface, HTML for an administration.
 */
interface ReportRenderer
{
    public function render(Report $report): string;
}
