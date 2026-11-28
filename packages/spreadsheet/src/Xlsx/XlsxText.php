<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Xlsx;

use Phil\Spreadsheet\Exception\WriteFailed;

/**
 * The texts of an XLSX workbook: escaped for XML, with the characters XML cannot hold — the control characters but the
 * tab and the line breaks, U+FFFE and U+FFFF — written `_xHHHH_`, as the format does, and a text that already looks
 * like such an escape protected by `_x005F_`.
 *
 * @internal
 */
final class XlsxText
{
    /** The most characters of a cell's text. */
    public const int LENGTH = 32767;

    private const string CONTROLS = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";

    /**
     * @throws WriteFailed when the text is not UTF-8
     */
    public static function escape(string $text): string
    {
        $escaped = htmlspecialchars($text, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');

        if ('' === $escaped && '' !== $text) {
            throw WriteFailed::because('A text to write is not UTF-8.');
        }

        if (str_contains($escaped, '_x')) {
            $escaped = (string) preg_replace('/_(x[0-9A-Fa-f]{4}_)/', '_x005F_$1', $escaped);
        }

        if (false !== strpbrk($escaped, self::CONTROLS) || str_contains($escaped, "\u{FFFE}") || str_contains($escaped, "\u{FFFF}")) {
            $escaped = (string) preg_replace_callback(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F]|\x{FFFE}|\x{FFFF}/u',
                static fn (array $match): string => \sprintf('_x%04X_', mb_ord($match[0], 'UTF-8')),
                $escaped,
            );
        }

        return $escaped;
    }

    /**
     * A text read from a workbook, its `_xHHHH_` escapes decoded.
     */
    public static function unescape(string $text): string
    {
        if (!str_contains($text, '_x')) {
            return $text;
        }

        return (string) preg_replace_callback(
            // A surrogate is no character: its escape stays as it is written.
            '/_x(?![dD][89a-fA-F][0-9A-Fa-f]{2})([0-9A-Fa-f]{4})_/',
            static fn (array $match): string => mb_chr((int) hexdec($match[1]), 'UTF-8'),
            $text,
        );
    }

    /**
     * Whether a text fits in a cell, within its 32,767 characters.
     */
    public static function fits(string $text): bool
    {
        return \strlen($text) <= self::LENGTH || mb_strlen($text, 'UTF-8') <= self::LENGTH;
    }
}
