<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Report;

use Phil\SvgChart\ChartType;

/**
 * The chart of a report: the key of a series source, the kind of chart, and its title — the source's when empty.
 */
final readonly class ReportChart
{
    public function __construct(
        public string $source,
        public ChartType $type = ChartType::Bar,
        public string $title = '',
    ) {
    }
}
