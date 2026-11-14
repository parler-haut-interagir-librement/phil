<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Contract;

use Phil\ReportingBundle\Application\Report\ReportChart;

/**
 * A report that has a chart: a series source drawn over the report's period, which the "mail" delivery puts in its
 * e-mail as a PNG image where PNG charts are drawn. Implemented beside `ReportDefinition`.
 */
interface ChartedReport
{
    public function chart(): ReportChart;
}
