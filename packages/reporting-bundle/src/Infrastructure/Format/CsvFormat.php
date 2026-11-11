<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Format;

use Override;
use Phil\ReportingBundle\Application\Contract\ReportFormat;
use Phil\ReportingBundle\Application\Report\ReportStream;

/**
 * Comma-separated values, RFC 4180, with the cells a spreadsheet would read as formulas neutralised.
 */
final readonly class CsvFormat implements ReportFormat
{
    #[Override]
    public static function key(): string
    {
        return 'csv';
    }

    #[Override]
    public function mediaType(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    #[Override]
    public function extension(): string
    {
        return 'csv';
    }

    #[Override]
    public function write(ReportStream $stream, array $columns, iterable $rows): void
    {
        new DelimitedWriter(',')->write($stream, $columns, $rows);
    }
}
