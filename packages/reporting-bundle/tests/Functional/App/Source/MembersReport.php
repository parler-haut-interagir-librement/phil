<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Functional\App\Source;

use Override;
use Phil\ReportingBundle\Application\Contract\ChartedReport;
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Report\ReportChart;
use Phil\ReportingBundle\Application\Report\ReportRequest;

/**
 * A report of the application: one row per member, the member's name typed by the member, and the chart of the
 * sign-ups.
 */
final class MembersReport implements ReportDefinition, ChartedReport
{
    #[Override]
    public static function key(): string
    {
        return 'members';
    }

    #[Override]
    public function title(): string
    {
        return 'Members';
    }

    #[Override]
    public function columns(ReportRequest $request): array
    {
        return ['Name', 'Since'];
    }

    #[Override]
    public function rows(ReportRequest $request): iterable
    {
        yield ['Ada', $request->period->start->format('Y-m-d')];
        yield ['=HYPERLINK("http://example.com")', $request->period->start->format('Y-m-d')];
    }

    #[Override]
    public function chart(): ReportChart
    {
        return new ReportChart('signups');
    }
}
