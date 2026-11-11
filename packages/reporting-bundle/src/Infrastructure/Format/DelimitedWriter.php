<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Format;

use Phil\ReportingBundle\Application\Report\ReportStream;

/**
 * The rows of a delimited file, RFC 4180 quoting, with every text cell a spreadsheet would read as a formula —
 * starting with `=`, `+`, `-`, `@`, a tab or a carriage return — prefixed with an apostrophe, so that a value typed by
 * a user never runs when the file is opened.
 *
 * @internal
 */
final readonly class DelimitedWriter
{
    public function __construct(
        private string $delimiter,
    ) {
    }

    /**
     * @param list<string>                               $columns
     * @param iterable<list<bool|float|int|string|null>> $rows
     */
    public function write(ReportStream $stream, array $columns, iterable $rows): void
    {
        $this->line($stream, $columns);

        foreach ($rows as $row) {
            $this->line($stream, $row);
        }
    }

    /**
     * @param list<bool|float|int|string|null> $cells
     */
    private function line(ReportStream $stream, array $cells): void
    {
        $fields = [];

        foreach ($cells as $cell) {
            $fields[] = match (true) {
                \is_bool($cell) => $cell ? 'true' : 'false',
                \is_string($cell) => $this->neutral($cell),
                default => $cell,
            };
        }

        $stream->writeFields($fields, $this->delimiter);
    }

    private function neutral(string $cell): string
    {
        return '' !== $cell && str_contains("=+-@\t\r", $cell[0]) ? "'".$cell : $cell;
    }
}
