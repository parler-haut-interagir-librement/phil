<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Tests\Unit\Fixture;

use Override;
use Phil\RouteAccessCheck\Contract\ReportFormatter;
use Phil\RouteAccessCheck\Report\Report;

/**
 * A format of the application's own: one line per route, its name and status.
 */
final readonly class CsvFormatter implements ReportFormatter
{
    #[Override]
    public function name(): string
    {
        return 'csv';
    }

    #[Override]
    public function format(Report $report, bool $onlyProblems = false): string
    {
        $lines = '';

        foreach ($report->verdicts as $verdict) {
            $lines .= $verdict->route->name.','.$verdict->status->value."\n";
        }

        return $lines;
    }
}
