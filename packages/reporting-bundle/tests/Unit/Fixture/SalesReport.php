<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use Override;
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Report\ReportRequest;

/**
 * A report of as many rows as its `rows` parameter, three by default, each with every kind of cell.
 */
final class SalesReport implements ReportDefinition
{
    #[Override]
    public static function key(): string
    {
        return 'sales';
    }

    #[Override]
    public function title(): string
    {
        return 'Sales';
    }

    #[Override]
    public function columns(ReportRequest $request): array
    {
        return ['Day', 'Amount', 'Paid', 'Note'];
    }

    #[Override]
    public function rows(ReportRequest $request): iterable
    {
        $rows = $request->parameter('rows', 3);

        for ($i = 1; $i <= $rows; ++$i) {
            yield [$request->period->start->format('Y-m-d'), $i * 1.5, 0 === $i % 2, 1 === $i ? '=SUM(A1)' : null];
        }
    }
}
