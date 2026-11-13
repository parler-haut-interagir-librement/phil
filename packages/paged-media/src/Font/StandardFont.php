<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Font;

/**
 * Helvetica and Helvetica Bold, which every PDF reader has, in the Windows-1252 encoding: the accents of French and the
 * other Western languages. Nothing is embedded, so a document written in them is small, but a character Windows-1252
 * lacks cannot be printed: the common ones print as their nearest equivalent and any other as `?`. A document that
 * must print any language embeds a TrueType font instead.
 */
enum StandardFont: string implements Font
{
    case Helvetica = 'Helvetica';
    case HelveticaBold = 'Helvetica-Bold';

    /** Helvetica's widths, in thousandths of the size, of the printable ASCII characters from the space to `~`. */
    private const array REGULAR_ASCII = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];

    /** Helvetica Bold's widths of the same characters. */
    private const array BOLD_ASCII = [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];

    /** Helvetica's widths of the Windows-1252 letters and signs above ASCII that text commonly holds, by byte. */
    private const array REGULAR_UPPER = [0x80 => 556, 0x85 => 1000, 0x91 => 222, 0x92 => 222, 0x93 => 333, 0x94 => 333, 0x95 => 350, 0x96 => 556, 0x97 => 1000, 0xA0 => 278, 0xAB => 556, 0xB0 => 400, 0xBB => 556, 0xC0 => 667, 0xC1 => 667, 0xC2 => 667, 0xC3 => 667, 0xC4 => 667, 0xC5 => 667, 0xC6 => 1000, 0xC7 => 722, 0xC8 => 667, 0xC9 => 667, 0xCA => 667, 0xCB => 667, 0xCC => 278, 0xCD => 278, 0xCE => 278, 0xCF => 278, 0xD0 => 722, 0xD1 => 722, 0xD2 => 778, 0xD3 => 778, 0xD4 => 778, 0xD5 => 778, 0xD6 => 778, 0xD7 => 584, 0xD8 => 778, 0xD9 => 722, 0xDA => 722, 0xDB => 722, 0xDC => 722, 0xDD => 667, 0xDE => 667, 0xDF => 611, 0xE0 => 556, 0xE1 => 556, 0xE2 => 556, 0xE3 => 556, 0xE4 => 556, 0xE5 => 556, 0xE6 => 889, 0xE7 => 500, 0xE8 => 556, 0xE9 => 556, 0xEA => 556, 0xEB => 556, 0xEC => 278, 0xED => 278, 0xEE => 278, 0xEF => 278, 0xF0 => 556, 0xF1 => 556, 0xF2 => 556, 0xF3 => 556, 0xF4 => 556, 0xF5 => 556, 0xF6 => 556, 0xF7 => 584, 0xF8 => 611, 0xF9 => 556, 0xFA => 556, 0xFB => 556, 0xFC => 556, 0xFD => 500, 0xFE => 556, 0xFF => 500];

    /** Helvetica Bold's widths of the same letters and signs. */
    private const array BOLD_UPPER = [0x80 => 556, 0x85 => 1000, 0x91 => 278, 0x92 => 278, 0x93 => 500, 0x94 => 500, 0x95 => 350, 0x96 => 556, 0x97 => 1000, 0xA0 => 278, 0xAB => 556, 0xB0 => 400, 0xBB => 556, 0xC0 => 722, 0xC1 => 722, 0xC2 => 722, 0xC3 => 722, 0xC4 => 722, 0xC5 => 722, 0xC6 => 1000, 0xC7 => 722, 0xC8 => 667, 0xC9 => 667, 0xCA => 667, 0xCB => 667, 0xCC => 278, 0xCD => 278, 0xCE => 278, 0xCF => 278, 0xD0 => 722, 0xD1 => 722, 0xD2 => 778, 0xD3 => 778, 0xD4 => 778, 0xD5 => 778, 0xD6 => 778, 0xD7 => 584, 0xD8 => 778, 0xD9 => 722, 0xDA => 722, 0xDB => 722, 0xDC => 722, 0xDD => 667, 0xDE => 667, 0xDF => 611, 0xE0 => 556, 0xE1 => 556, 0xE2 => 556, 0xE3 => 556, 0xE4 => 556, 0xE5 => 556, 0xE6 => 889, 0xE7 => 556, 0xE8 => 556, 0xE9 => 556, 0xEA => 556, 0xEB => 556, 0xEC => 278, 0xED => 278, 0xEE => 278, 0xEF => 278, 0xF0 => 611, 0xF1 => 611, 0xF2 => 611, 0xF3 => 611, 0xF4 => 611, 0xF5 => 611, 0xF6 => 611, 0xF7 => 584, 0xF8 => 611, 0xF9 => 611, 0xFA => 611, 0xFB => 611, 0xFC => 611, 0xFD => 556, 0xFE => 611, 0xFF => 556];

    /** The width counted for any other byte: as wide as the widest letters, so that a measured text never runs over. */
    private const int UNKNOWN_WIDTH = 1000;

    /**
     * Characters that Windows-1252 lacks, written as the nearest one it holds rather than as `?`: the minus sign and
     * the dashes of prices and labels, and the narrow and thin spaces that French puts before `%` and `$`.
     */
    private const array SUBSTITUTES = [
        "\u{2212}" => '-',
        "\u{2010}" => '-',
        "\u{2011}" => '-',
        "\u{2012}" => "\u{2013}",
        "\u{202F}" => "\u{00A0}",
        "\u{2007}" => "\u{00A0}",
        "\u{2009}" => ' ',
        "\u{200A}" => ' ',
        "\u{2002}" => ' ',
        "\u{2003}" => ' ',
    ];

    public function width(string $text, float $size): float
    {
        $widths = self::Helvetica === $this ? array_combine(range(32, 126), self::REGULAR_ASCII) + self::REGULAR_UPPER : array_combine(range(32, 126), self::BOLD_ASCII) + self::BOLD_UPPER;
        $width = 0;

        foreach (str_split(self::encode($text)) as $byte) {
            $width += $widths[\ord($byte[0])] ?? self::UNKNOWN_WIDTH;
        }

        return $width * $size / 1000;
    }

    /**
     * The text in Windows-1252, the encoding of the fonts: the characters it lacks are first written as their nearest
     * equivalents, and what is still missing becomes `?`.
     *
     * @internal
     */
    public static function encode(string $text): string
    {
        return mb_convert_encoding(strtr($text, self::SUBSTITUTES), 'Windows-1252', 'UTF-8');
    }
}
