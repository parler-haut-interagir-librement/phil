<?php

declare(strict_types=1);

namespace Phil\Citations\Internal;

use Normalizer;

/**
 * The text of a BibTeX value made plain: LaTeX accents to composed Unicode letters (`\'e`, `{\"o}`, `\c{c}`,
 * `\v s`), the letters LaTeX names (`\ss`, `\o`, `{\aa}`, `\i`), the escaped characters, the dashes and the tie,
 * the braces that protect case left out, and runs of white space made one space. An unknown command keeps its
 * argument and loses its name.
 *
 * @internal
 */
final class LatexText
{
    private const array ACCENTS = [
        '`' => "\u{0300}", "'" => "\u{0301}", '^' => "\u{0302}", '~' => "\u{0303}", '=' => "\u{0304}",
        'u' => "\u{0306}", '.' => "\u{0307}", '"' => "\u{0308}", 'r' => "\u{030A}", 'H' => "\u{030B}",
        'v' => "\u{030C}", 'd' => "\u{0323}", 'c' => "\u{0327}", 'k' => "\u{0328}", 'b' => "\u{0331}",
    ];

    private const array LETTERS = [
        'ss' => 'ß', 'ae' => 'æ', 'AE' => 'Æ', 'oe' => 'œ', 'OE' => 'Œ', 'aa' => 'å', 'AA' => 'Å',
        'o' => 'ø', 'O' => 'Ø', 'l' => 'ł', 'L' => 'Ł', 'i' => 'ı', 'j' => 'ȷ', 'dh' => 'ð', 'DH' => 'Ð',
        'th' => 'þ', 'TH' => 'Þ', 'ng' => 'ŋ', 'NG' => 'Ŋ', 'textendash' => '–', 'textemdash' => '—',
        'textquoteleft' => '‘', 'textquoteright' => '’', 'textquotedblleft' => '“', 'textquotedblright' => '”',
        'guillemotleft' => '«', 'guillemotright' => '»', 'textellipsis' => '…', 'dots' => '…', 'ldots' => '…',
        'S' => '§', 'P' => '¶', 'copyright' => '©', 'textregistered' => '®', 'texttrademark' => '™',
        'euro' => '€', 'pounds' => '£', 'textdegree' => '°', 'LaTeX' => 'LaTeX', 'TeX' => 'TeX',
    ];

    private const array ESCAPES = ['&', '%', '$', '#', '_', '{', '}', '\\'];

    public static function plain(string $latex): string
    {
        $text = '';
        $length = \strlen($latex);
        $position = 0;

        while ($position < $length) {
            $char = $latex[$position];

            if ('\\' === $char) {
                [$piece, $position] = self::command($latex, $position + 1);
                $text .= $piece;

                continue;
            }

            $text .= match ($char) {
                '{', '}' => '',
                '~' => "\u{00A0}",
                default => $char,
            };
            ++$position;
        }

        $text = strtr($text, ['---' => '—', '--' => '–', '``' => '“', "''" => '”']);
        $text = trim((string) preg_replace('/[ \t\r\n]+/', ' ', $text));

        return (string) Normalizer::normalize($text, Normalizer::FORM_C);
    }

    /**
     * @return array{string, int} the text of the command, and the position after it
     */
    private static function command(string $latex, int $position): array
    {
        $length = \strlen($latex);

        if ($position >= $length) {
            return ['', $position];
        }

        $char = $latex[$position];

        if (\in_array($char, self::ESCAPES, true)) {
            return ['\\' === $char ? ' ' : $char, $position + 1];
        }

        if (!ctype_alpha($char)) {
            if (isset(self::ACCENTS[$char])) {
                return self::accent(self::ACCENTS[$char], $latex, $position + 1);
            }

            return [$char, $position + 1];
        }

        $end = $position;

        while ($end < $length && ctype_alpha($latex[$end])) {
            ++$end;
        }

        $name = substr($latex, $position, $end - $position);

        if (isset(self::ACCENTS[$name])) {
            return self::accent(self::ACCENTS[$name], $latex, self::skipSpaces($latex, $end));
        }

        if (isset(self::LETTERS[$name])) {
            return [self::LETTERS[$name], self::skipSpaces($latex, $end)];
        }

        return ['', self::skipSpaces($latex, $end)];
    }

    /**
     * @return array{string, int}
     */
    private static function accent(string $mark, string $latex, int $position): array
    {
        $length = \strlen($latex);
        $braced = $position < $length && '{' === $latex[$position];
        $start = $braced ? $position + 1 : $position;

        if ($start < $length && '\\' === $latex[$start]) {
            [$base, $after] = self::command($latex, $start + 1);
        } else {
            $base = mb_substr(substr($latex, $start, 4), 0, 1);
            $after = $start + \strlen($base);
        }

        if ($braced && $after < $length && '}' === $latex[$after]) {
            ++$after;
        }

        return [strtr($base, ['ı' => 'i', 'ȷ' => 'j']).$mark, $after];
    }

    private static function skipSpaces(string $latex, int $position): int
    {
        return $position + strspn($latex, ' ', $position);
    }
}
