<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Output;

use Phil\ImportWizard\Csv\CsvDialect;
use Phil\ImportWizard\Csv\CsvWriter;
use Phil\ImportWizard\Plan\ImportPlan;
use Phil\ImportWizard\Plan\RowRejection;

/**
 * The rows refused, as a CSV file to correct and import again: two columns first, the line of each row in the file
 * imported and its errors, one per cell, then the headers and cells of the file as they were written. Imported again
 * with the same profile, the two first columns map to nothing and are ignored.
 */
final class RejectedRowsFile
{
    public const string LINE_HEADER = 'import_line';

    public const string ERRORS_HEADER = 'import_errors';

    private function __construct()
    {
    }

    /**
     * @param list<RowRejection>|null $rejections those of an `ImportResult`, the plan's when null
     */
    public static function csv(ImportPlan $plan, ?array $rejections = null, CsvDialect $dialect = new CsvDialect()): string
    {
        $width = \count($plan->headers);
        $rows = [[self::LINE_HEADER, self::ERRORS_HEADER, ...$plan->headers]];

        foreach ($rejections ?? $plan->rejections as $rejection) {
            $errors = implode(' | ', array_map($plan->describe(...), $rejection->errors));
            $rows[] = [(string) $rejection->line, $errors, ...array_pad($rejection->cells, $width, '')];
        }

        return CsvWriter::write($rows, $dialect);
    }
}
