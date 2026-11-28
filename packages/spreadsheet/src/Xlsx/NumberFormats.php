<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Xlsx;

use Phil\Spreadsheet\CellType;

/**
 * Which number formats of an XLSX workbook show a date: a serial number under one of them is read as a date, under the
 * others as a number.
 *
 * @internal
 */
final class NumberFormats
{
    /** The built-in formats of a day: 14 to 17, and those of the East Asian locales. */
    private const array BUILT_IN_DATES = [14, 15, 16, 17, 27, 28, 29, 30, 31, 34, 35, 36, 50, 51, 52, 53, 54, 57, 58];

    /** The built-in formats of a time of day, or of a day and a time. */
    private const array BUILT_IN_TIMES = [18, 19, 20, 21, 22, 32, 33, 45, 46, 47, 55, 56];

    /**
     * Date for a format of a day, DateTime for one with a time of day, null for any other.
     *
     * @param string|null $code the format's code, for a format the workbook declares
     */
    public static function kind(int $id, ?string $code): ?CellType
    {
        if (null === $code) {
            return match (true) {
                \in_array($id, self::BUILT_IN_DATES, true) => CellType::Date,
                \in_array($id, self::BUILT_IN_TIMES, true) => CellType::DateTime,
                default => null,
            };
        }

        // The texts in quotes, the escaped characters, the colours, the conditions and the elapsed times are no date.
        $code = (string) preg_replace('/"[^"]*"|\\\\.|\[[^\]]*\]|_.|\*./', '', $code);
        $code = strtolower(explode(';', $code, 2)[0]);

        if (str_contains($code, 'am/pm') || str_contains($code, 'a/p')) {
            return CellType::DateTime;
        }

        $day = 1 === preg_match('/[dy]/', $code);
        $time = 1 === preg_match('/[hs]/', $code);

        return match (true) {
            $time => CellType::DateTime,
            $day, str_contains($code, 'm') => CellType::Date,
            default => null,
        };
    }
}
