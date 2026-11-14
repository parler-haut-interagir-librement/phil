<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use Override;
use Phil\ReportingBundle\Application\Contract\ChartedReport;
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Report\ReportChart;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Phil\SvgChart\ChartType;

/**
 * A report of one row with a chart: the visits as lines, under the title given, the source's when empty.
 */
final readonly class ChartedSalesReport implements ReportDefinition, ChartedReport
{
    public function __construct(private string $chartTitle = '', private string $source = 'visits')
    {
    }

    #[Override]
    public static function key(): string
    {
        return 'charted';
    }

    #[Override]
    public function title(): string
    {
        return 'Sales & visits';
    }

    #[Override]
    public function columns(ReportRequest $request): array
    {
        return ['Day'];
    }

    #[Override]
    public function rows(ReportRequest $request): iterable
    {
        yield [$request->period->start->format('Y-m-d')];
    }

    #[Override]
    public function chart(): ReportChart
    {
        return new ReportChart($this->source, ChartType::Line, $this->chartTitle);
    }
}
