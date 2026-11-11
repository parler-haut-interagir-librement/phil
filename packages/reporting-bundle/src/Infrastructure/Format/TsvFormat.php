<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Format;

use Override;
use Phil\ReportingBundle\Application\Contract\ReportFormat;
use Phil\ReportingBundle\Application\Report\ReportStream;

/**
 * Tab-separated values, quoted as CSV is, with the cells a spreadsheet would read as formulas neutralised.
 */
final readonly class TsvFormat implements ReportFormat
{
    #[Override]
    public static function key(): string
    {
        return 'tsv';
    }

    #[Override]
    public function mediaType(): string
    {
        return 'text/tab-separated-values; charset=UTF-8';
    }

    #[Override]
    public function extension(): string
    {
        return 'tsv';
    }

    #[Override]
    public function write(ReportStream $stream, array $columns, iterable $rows): void
    {
        new DelimitedWriter("\t")->write($stream, $columns, $rows);
    }
}
