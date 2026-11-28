<?php

declare(strict_types=1);

namespace Phil\Spreadsheet;

use Phil\Spreadsheet\Exception\InvalidSpreadsheet;

/**
 * The formats the library reads and writes.
 *
 * A file's extension or the media type a browser sent only chooses the reader: the reader checks the bytes themselves.
 */
enum Format: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Ods = 'ods';

    public function extension(): string
    {
        return $this->value;
    }

    public function mediaType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Ods => 'application/vnd.oasis.opendocument.spreadsheet',
        };
    }

    /**
     * The format of an extension (`xlsx`, `.XLSX`), of a file name or path (`report.ods`), or of a media type
     * (`text/csv; charset=utf-8`).
     *
     * @throws InvalidSpreadsheet when none of the formats matches
     */
    public static function detect(string $nameOrMediaType): self
    {
        $value = strtolower(trim($nameOrMediaType));
        $dot = strrpos($value, '.');

        $format = match (trim(explode(';', $value, 2)[0])) {
            'text/csv', 'application/csv' => self::Csv,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => self::Xlsx,
            'application/vnd.oasis.opendocument.spreadsheet' => self::Ods,
            default => self::tryFrom(false === $dot ? $value : substr($value, $dot + 1)),
        };

        return $format ?? throw InvalidSpreadsheet::because(\sprintf('"%s" names none of the formats csv, xlsx and ods.', mb_scrub(mb_substr($nameOrMediaType, 0, 100), 'UTF-8')));
    }
}
