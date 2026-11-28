<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Xlsx;

/**
 * The letters of a column: `A` for the first, `Z`, `AA`, up to `XFD` for the 16,384th.
 *
 * @internal
 */
final class CellReference
{
    /**
     * @param int $column from 1
     */
    public static function letters(int $column): string
    {
        $letters = '';

        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letters = \chr(65 + $remainder).$letters;
            $column = intdiv($column - 1, 26);
        }

        return $letters;
    }

    /**
     * The column of a reference such as `B12` or `b12`, from 1; null when it does not start with one to three letters.
     */
    public static function column(string $reference): ?int
    {
        if (1 !== preg_match('/^([A-Za-z]{1,3})\d*$/D', $reference, $match)) {
            return null;
        }

        $column = 0;

        foreach (str_split(strtoupper($match[1])) as $letter) {
            $column = $column * 26 + \ord($letter[0]) - 64;
        }

        return $column;
    }
}
