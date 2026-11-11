<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Contract;

use Phil\ReportingBundle\Application\Report\ReportStream;

/**
 * A file format a report is exported in. Every service implementing it is a format, found by its key.
 */
interface ReportFormat
{
    /**
     * The format's key, unique among the formats: `csv`, `tsv`, `json`.
     */
    public static function key(): string;

    /**
     * The media type of the file, `text/csv; charset=UTF-8`.
     */
    public function mediaType(): string;

    /**
     * The extension of the file's name, without its dot.
     */
    public function extension(): string;

    /**
     * Writes the headings and the rows to the stream, as they come.
     *
     * @param list<string>                               $columns
     * @param iterable<list<bool|float|int|string|null>> $rows
     */
    public function write(ReportStream $stream, array $columns, iterable $rows): void;
}
