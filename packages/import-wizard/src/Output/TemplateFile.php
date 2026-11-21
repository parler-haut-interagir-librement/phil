<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Output;

use Phil\ImportWizard\Csv\CsvDialect;
use Phil\ImportWizard\Csv\CsvWriter;
use Phil\ImportWizard\Profile\ImportProfile;

/**
 * The template of an importable type: a CSV file holding only the header of each column of its profile, in order, for
 * the person who imports to fill.
 */
final class TemplateFile
{
    private function __construct()
    {
    }

    public static function csv(ImportProfile $profile, CsvDialect $dialect = new CsvDialect()): string
    {
        $headers = [];

        foreach ($profile->columns as $column) {
            $headers[] = $column->header;
        }

        return CsvWriter::write([$headers], $dialect);
    }

    /**
     * The name to offer the template under: the type and `-template.csv`.
     */
    public static function filename(ImportProfile $profile): string
    {
        return $profile->type.'-template.csv';
    }
}
