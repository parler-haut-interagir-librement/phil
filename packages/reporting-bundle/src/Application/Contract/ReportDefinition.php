<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Contract;

use Phil\ReportingBundle\Application\Report\ReportRequest;

/**
 * A report: the columns and the rows of a period, exported in any format. Every service implementing it is a report,
 * found by its key.
 *
 * The rows are read as they are written: yield them from a query iterated row by row, and an export of a hundred
 * thousand rows holds one in memory at a time.
 */
interface ReportDefinition
{
    /**
     * The report's key, unique among the reports: letters, digits, dots, dashes and underscores.
     */
    public static function key(): string;

    public function title(): string;

    /**
     * The headings of the columns, in their order.
     *
     * @return list<string>
     */
    public function columns(ReportRequest $request): array;

    /**
     * The rows, each with one cell per column.
     *
     * @return iterable<list<bool|float|int|string|null>>
     */
    public function rows(ReportRequest $request): iterable;
}
