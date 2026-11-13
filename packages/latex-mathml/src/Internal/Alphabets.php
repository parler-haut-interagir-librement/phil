<?php

declare(strict_types=1);

namespace Phil\LatexMathMl\Internal;

/**
 * Latin letters and digits in the Mathematical Alphanumeric Symbols of Unicode, which MathML Core draws in place of
 * the `mathvariant` attribute: 𝐀 for a bold A, ℝ for a double-struck R. The letters Unicode placed earlier, in the
 * Letterlike Symbols, are taken from there.
 *
 * @internal
 */
final class Alphabets
{
    /** Each alphabet, to the code points of its A, its a and its 0, or null where it has no digits. */
    private const array STARTS = [
        'bold' => [0x1D400, 0x1D41A, 0x1D7CE],
        'italic' => [0x1D434, 0x1D44E, null],
        'script' => [0x1D49C, 0x1D4B6, null],
        'fraktur' => [0x1D504, 0x1D51E, null],
        'double-struck' => [0x1D538, 0x1D552, 0x1D7D8],
        'sans-serif' => [0x1D5A0, 0x1D5BA, 0x1D7E2],
        'monospace' => [0x1D670, 0x1D68A, 0x1D7F6],
    ];

    /** The letters of an alphabet that sit in the Letterlike Symbols block. */
    private const array HOLES = [
        'italic' => ['h' => 'ℎ'],
        'script' => ['B' => 'ℬ', 'E' => 'ℰ', 'F' => 'ℱ', 'H' => 'ℋ', 'I' => 'ℐ', 'L' => 'ℒ', 'M' => 'ℳ', 'R' => 'ℛ', 'e' => 'ℯ', 'g' => 'ℊ', 'o' => 'ℴ'],
        'fraktur' => ['C' => 'ℭ', 'H' => 'ℌ', 'I' => 'ℑ', 'R' => 'ℜ', 'Z' => 'ℨ'],
        'double-struck' => ['C' => 'ℂ', 'H' => 'ℍ', 'N' => 'ℕ', 'P' => 'ℙ', 'Q' => 'ℚ', 'R' => 'ℝ', 'Z' => 'ℤ'],
    ];

    /**
     * The text with its Latin letters, and its digits where the alphabet has them, in the alphabet; any other
     * character, and every character for `normal`, is kept.
     */
    public static function apply(string $text, string $alphabet): string
    {
        $starts = self::STARTS[$alphabet] ?? null;

        if (null === $starts) {
            return $text;
        }

        return (string) preg_replace_callback('/[A-Za-z0-9]/', static function (array $match) use ($alphabet, $starts): string {
            $char = $match[0];

            if (isset(self::HOLES[$alphabet][$char])) {
                return self::HOLES[$alphabet][$char];
            }

            $code = \ord($char[0]);
            [$upper, $lower, $digit] = $starts;
            $start = match (true) {
                $code <= 0x39 => null === $digit ? null : $digit + $code - 0x30,
                $code <= 0x5A => $upper + $code - 0x41,
                default => $lower + $code - 0x61,
            };

            return null === $start ? $char : mb_chr($start, 'UTF-8');
        }, $text);
    }
}
