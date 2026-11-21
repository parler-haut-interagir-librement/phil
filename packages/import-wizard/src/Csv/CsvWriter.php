<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Csv;

/**
 * Writes rows as RFC 4180 CSV, in UTF-8 with CRLF line ends: a cell is quoted when it holds the delimiter, the quote, a
 * line break or a space at either end. With the guard of the dialect, a cell a spreadsheet would run as a formula —
 * starting with `=`, `+`, `-`, `@`, a tab or a carriage return, and not a number — is written after an apostrophe.
 */
final class CsvWriter
{
    private function __construct()
    {
    }

    /**
     * @param iterable<list<string>> $rows
     */
    public static function write(iterable $rows, CsvDialect $dialect = new CsvDialect()): string
    {
        $csv = $dialect->byteOrderMark ? "\u{FEFF}" : '';

        foreach ($rows as $row) {
            $cells = [];

            foreach ($row as $cell) {
                $cells[] = self::cell($cell, $dialect);
            }

            $csv .= implode($dialect->delimiter, $cells)."\r\n";
        }

        return $csv;
    }

    private static function cell(string $cell, CsvDialect $dialect): string
    {
        if ($dialect->guardFormulas && 1 === preg_match('/^[=+\-@\t\r]/', $cell) && 1 !== preg_match('/^[+-]?\d+(?:[.,]\d+)?$/D', $cell)) {
            $cell = "'".$cell;
        }

        $quote = $dialect->enclosure;

        if ('' === $cell || false === strpbrk($cell, $dialect->delimiter.$quote."\r\n") && trim($cell) === $cell) {
            return $cell;
        }

        return $quote.str_replace($quote, $quote.$quote, $cell).$quote;
    }
}
