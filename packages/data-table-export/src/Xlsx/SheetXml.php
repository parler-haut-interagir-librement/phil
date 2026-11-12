<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Xlsx;

use DateTimeInterface;
use Phil\DataTableExport\Cells;

/**
 * The XML of the cells of a worksheet. A text is an inline string — never a formula, so nothing a person typed runs
 * when the file opens —, kept to valid UTF-8 and to the characters XML 1.0 allows, and cut at the 32,767 characters a
 * cell of a spreadsheet holds. A date is a serial number of days since 1900, styled as a date or a date and time; a
 * date before March 1900, which spreadsheets count wrong, is written as text.
 *
 * @internal
 */
final class SheetXml
{
    /** The most characters a cell holds. */
    public const int CELL_LIMIT = 32767;

    /** The style of a heading, of a date, and of a date and time, in the styles part. */
    public const int HEADING_STYLE = 1;

    public const int DATE_STYLE = 2;

    public const int DATE_TIME_STYLE = 3;

    /** The serial of 1900-03-01, the first day a spreadsheet counts right. */
    private const int FIRST_SERIAL = 61;

    /** The serial of 1970-01-01. */
    private const int EPOCH_SERIAL = 25569;

    private const int DAY = 86400;

    /**
     * `A` for 1, `Z` for 26, `AA` for 27.
     */
    public static function column(int $number): string
    {
        $letters = '';

        for ($left = $number; $left > 0; $left = intdiv($left - 1, 26)) {
            $letters = \chr(65 + ($left - 1) % 26).$letters;
        }

        return $letters;
    }

    /**
     * Text an XML document may hold, its markup escaped.
     */
    public static function text(string $text): string
    {
        $valid = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', mb_scrub($text, 'UTF-8')) ?? '';

        return htmlspecialchars($valid, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }

    /**
     * The element of one cell, empty when the cell holds nothing.
     */
    public static function cell(string $reference, mixed $cell, int $style = 0): string
    {
        $value = Cells::value($cell);
        $styled = 0 === $style ? '' : ' s="'.$style.'"';

        return match (true) {
            null === $value, '' === $value => '',
            \is_bool($value) => '<c r="'.$reference.'" t="b"'.$styled.'><v>'.($value ? '1' : '0').'</v></c>',
            \is_int($value) => '<c r="'.$reference.'"'.$styled.'><v>'.$value.'</v></c>',
            \is_float($value) => is_finite($value) ? '<c r="'.$reference.'"'.$styled.'><v>'.$value.'</v></c>' : self::string($reference, (string) $value, $styled),
            $value instanceof DateTimeInterface => self::date($reference, $value),
            default => self::string($reference, $value, $styled),
        };
    }

    private static function string(string $reference, string $text, string $styled): string
    {
        $cut = mb_substr($text, 0, self::CELL_LIMIT);

        return '<c r="'.$reference.'" t="inlineStr"'.$styled.'><is><t xml:space="preserve">'.self::text($cut).'</t></is></c>';
    }

    private static function date(string $reference, DateTimeInterface $date): string
    {
        $seconds = $date->getTimestamp() + $date->getOffset();
        $days = intdiv($seconds, self::DAY) - ($seconds % self::DAY < 0 ? 1 : 0);
        $time = $seconds - $days * self::DAY;
        $serial = $days + self::EPOCH_SERIAL;

        if ($serial < self::FIRST_SERIAL) {
            return self::string($reference, Cells::date($date), '');
        }

        if (0 === $time) {
            return '<c r="'.$reference.'" s="'.self::DATE_STYLE.'"><v>'.$serial.'</v></c>';
        }

        $fraction = rtrim(substr(\sprintf('%.10F', $time / self::DAY), 1), '0');

        return '<c r="'.$reference.'" s="'.self::DATE_TIME_STYLE.'"><v>'.$serial.$fraction.'</v></c>';
    }
}
