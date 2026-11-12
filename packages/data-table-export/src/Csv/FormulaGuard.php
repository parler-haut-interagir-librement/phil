<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Csv;

/**
 * Keeps a spreadsheet from reading a cell of a CSV file as a formula. A text that starts with `=`, `+`, `-`, `@`, a tab
 * or a carriage return — or with one of them after spaces, which some spreadsheets skip — gets a leading apostrophe,
 * which the spreadsheet shows as text. A number written as text, `-12` or `+3.5`, stays as it is: no spreadsheet
 * runs it.
 */
final class FormulaGuard
{
    private const string TRIGGERS = "=+-@\t\r";

    private const string NUMBER = '/^[+-]?(\d+([.,]\d+)?|[.,]\d+)([eE][+-]?\d+)?$/D';

    public static function protect(string $text): string
    {
        $start = ltrim($text, ' ');

        if ('' === $start || !str_contains(self::TRIGGERS, $start[0]) || 1 === preg_match(self::NUMBER, $text)) {
            return $text;
        }

        return "'".$text;
    }
}
