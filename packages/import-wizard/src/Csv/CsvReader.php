<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Csv;

use Phil\ImportWizard\Exception\SourceRefused;
use Phil\ImportWizard\Source\SourceLimits;
use Phil\ImportWizard\Source\SourceRow;

/**
 * Reads the records of CSV text as RFC 4180 writes them: cells quoted or not, quotes doubled inside a quoted cell, line
 * breaks inside it, records ended by CRLF, LF or CR. Each record keeps the physical line it starts on. A quote left
 * open, too many cells or too many records refuse the whole text; text after a closing quote only marks its record as
 * malformed, kept as written.
 */
final class CsvReader
{
    private const string FORMULA_STARTS = "=+-@\t\r";

    private function __construct()
    {
    }

    /**
     * @return list<SourceRow> every record, the header first
     */
    public static function parse(string $content, CsvDialect $dialect, SourceLimits $limits): array
    {
        $rows = [];
        $length = \strlen($content);
        $position = 0;
        $line = 1;

        while ($position < $length) {
            $start = $line;
            $cells = [];
            $malformed = false;

            do {
                [$cell, $position, $line, $broken] = self::cell($content, $position, $line, $dialect);
                $cells[] = $dialect->guardFormulas ? self::unguard($cell) : $cell;
                $malformed = $malformed || $broken;

                if (\count($cells) > $limits->maxColumns) {
                    throw SourceRefused::because(\sprintf('The record on line %d has more than %d cells.', $start, $limits->maxColumns));
                }

                $next = $content[$position] ?? '';
                ++$position;
            } while ($next === $dialect->delimiter);

            if ("\r" === $next && "\n" === ($content[$position] ?? '')) {
                ++$position;
            }

            ++$line;
            $rows[] = new SourceRow($start, $cells, $malformed);

            if (\count($rows) > $limits->maxRows + 1) {
                throw SourceRefused::because(\sprintf('The file has more than %d rows.', $limits->maxRows));
            }
        }

        return $rows;
    }

    /**
     * @return array{string, int, int, bool} the cell, the position after it, the line there, whether it is malformed
     */
    private static function cell(string $content, int $position, int $line, CsvDialect $dialect): array
    {
        $stops = $dialect->delimiter."\r\n";

        if (($content[$position] ?? '') !== $dialect->enclosure) {
            $width = strcspn($content, $stops, $position);

            return [substr($content, $position, $width), $position + $width, $line, false];
        }

        $start = $line;
        $cell = '';
        ++$position;

        while (true) {
            $close = strpos($content, $dialect->enclosure, $position);

            if (false === $close) {
                throw SourceRefused::because(\sprintf('The quote opened on line %d is never closed.', $start));
            }

            $chunk = substr($content, $position, $close - $position);
            $cell .= $chunk;
            $line += substr_count($chunk, "\n") + substr_count($chunk, "\r") - substr_count($chunk, "\r\n");
            $position = $close + 1;

            if (($content[$position] ?? '') !== $dialect->enclosure) {
                break;
            }

            $cell .= $dialect->enclosure;
            ++$position;
        }

        $rest = strcspn($content, $stops, $position);

        return [$cell.substr($content, $position, $rest), $position + $rest, $line, $rest > 0];
    }

    private static function unguard(string $cell): string
    {
        return "'" === ($cell[0] ?? '') && str_contains(self::FORMULA_STARTS, $cell[1] ?? "'") ? substr($cell, 1) : $cell;
    }
}
