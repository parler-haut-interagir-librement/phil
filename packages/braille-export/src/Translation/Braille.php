<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Translation;

use InvalidArgumentException;

/**
 * Unicode braille patterns, the form every text of the package takes once translated: one character of U+2800 to
 * U+28FF per cell, its dots the bits of the code point, dot 1 the lowest. Each takes three bytes in UTF-8, which the
 * layout counts on.
 */
final class Braille
{
    /** The blank cell, between words. */
    public const string BLANK = "\u{2800}";

    /** Dots 3-6: the hyphen of a word cut at the end of a line, in English and in French. */
    public const string HYPHEN = "\u{2824}";

    private const int BYTES = 3;

    private function __construct()
    {
    }

    /**
     * The cell of some dots, such as `1245` for the letter g; an empty string is the blank cell.
     */
    public static function cell(string $dots): string
    {
        if (1 !== preg_match('/^(?!.*(.).*\1)[1-8]{0,8}$/D', $dots)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a set of dots from 1 to 8, each once.', $dots));
        }

        $mask = 0;

        foreach (str_split($dots) as $dot) {
            $mask |= 1 << ((int) $dot - 1);
        }

        return mb_chr(0x2800 + $mask, 'UTF-8');
    }

    /**
     * The cells of a sign of several cells, such as `cells('6', '6')` for two capital signs.
     */
    public static function cells(string ...$dots): string
    {
        return implode('', array_map(self::cell(...), $dots));
    }

    /**
     * Whether a text holds braille cells and nothing else; an empty text does.
     */
    public static function isBraille(string $text): bool
    {
        return 1 === preg_match('/^[\x{2800}-\x{28FF}]*+$/Du', $text);
    }

    /**
     * The number of cells of a text of braille cells.
     */
    public static function length(string $braille): int
    {
        return intdiv(\strlen($braille), self::BYTES);
    }
}
