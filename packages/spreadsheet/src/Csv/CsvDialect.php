<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Csv;

use Phil\Spreadsheet\Exception\InvalidSpreadsheet;

/**
 * The shape of a CSV file: its delimiter, its quote, its line ending and its byte order mark. The defaults follow RFC
 * 4180, with the UTF-8 byte order mark spreadsheets need to read the accents right.
 */
final readonly class CsvDialect
{
    /**
     * @param string $delimiter     one byte between cells
     * @param string $enclosure     one byte around a cell that holds the delimiter, a quote or a line break, doubled inside
     * @param string $lineEnding    `\r\n` or `\n`, written after each row
     * @param bool   $byteOrderMark whether the file written starts with the UTF-8 byte order mark; one is skipped on
     *                              reading either way
     *
     * @throws InvalidSpreadsheet when the delimiter or the enclosure is not one byte, they are the same, or one is a
     *                            line break; or the line ending is another
     */
    public function __construct(
        public string $delimiter = ',',
        public string $enclosure = '"',
        public string $lineEnding = "\r\n",
        public bool $byteOrderMark = true,
    ) {
        if (1 !== \strlen($delimiter) || 1 !== \strlen($enclosure) || $delimiter === $enclosure || str_contains("\r\n", $delimiter) || str_contains("\r\n", $enclosure)) {
            throw InvalidSpreadsheet::because('The delimiter and the enclosure of a CSV dialect are two different bytes, neither a line break.');
        }

        if ("\r\n" !== $lineEnding && "\n" !== $lineEnding) {
            throw InvalidSpreadsheet::because('The line ending of a CSV dialect is "\r\n" or "\n".');
        }
    }

    /**
     * The semicolon dialect of spreadsheets set to a language that writes decimals with a comma.
     */
    public static function semicolon(): self
    {
        return new self(';');
    }
}
