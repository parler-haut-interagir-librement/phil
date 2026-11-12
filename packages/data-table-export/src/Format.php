<?php

declare(strict_types=1);

namespace Phil\DataTableExport;

use Phil\DataTableExport\Exception\ExportRefused;

/**
 * The formats a sheet is written in, with the extension and the media type of their file.
 */
enum Format: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    /**
     * @throws ExportRefused when no format has the name
     */
    public static function named(string $name): self
    {
        return self::tryFrom(strtolower($name)) ?? throw ExportRefused::because(\sprintf('"%s" is not a format: csv or xlsx.', $name));
    }

    public function mediaType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv; charset=UTF-8',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }

    /**
     * The name of a file, `books.csv` for `books`: the name kept to letters, digits, `-` and `_`, of at most 100
     * characters, `export` when nothing is left of it.
     */
    public function fileName(string $name): string
    {
        $kept = trim(substr((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), 0, 100), '-');

        return ('' === $kept ? 'export' : $kept).'.'.$this->value;
    }
}
