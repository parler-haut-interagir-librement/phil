<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Layout\Internal;

use Closure;
use Generator;
use Phil\BrailleExport\Translation\Braille;

/**
 * Braille words cut into lines.
 *
 * A word goes whole to the next line when it does not fit; a word longer than a line is cut, after a hyphen it holds
 * when one fits, so that no second hyphen is added, else before the last cell of the line with the hyphen dots 3-6
 * added, never right after a cell that announces the next one, such as a capital or a number sign.
 *
 * @internal
 */
final class Flow
{
    /** The cells that announce the next: capital, number, grade 1 and the prefixes of composed signs. */
    private const array INDICATORS = ["\u{2820}", "\u{2828}", "\u{283C}", "\u{2830}", "\u{2818}", "\u{2838}", "\u{2810}", "\u{2808}"];

    private const int BYTES = 3;

    private function __construct()
    {
    }

    /**
     * The words of a text of braille cells, the blank cells between them left out.
     *
     * @return list<string>
     */
    public static function words(string $braille): array
    {
        return array_values(array_filter(explode(Braille::BLANK, $braille), static fn (string $word): bool => '' !== $word));
    }

    /**
     * The lines of some words, each line as wide as `$width` says when it starts: the number of the line, from 0, to
     * the cells it may hold, 2 or more.
     *
     * @param list<string>      $words
     * @param Closure(int): int $width
     *
     * @return Generator<int, string>
     */
    public static function lines(array $words, Closure $width): Generator
    {
        $number = 0;
        $available = $width($number);
        $line = '';
        $length = 0;

        foreach ($words as $word) {
            $start = 0;
            $bytes = \strlen($word);

            while ($start < $bytes) {
                $size = intdiv($bytes - $start, self::BYTES);
                $gap = $length > 0 ? 1 : 0;

                if ($length + $gap + $size <= $available) {
                    $line .= str_repeat(Braille::BLANK, $gap).substr($word, $start);
                    $length += $gap + $size;

                    break;
                }

                $cut = self::afterHyphen($word, $start, $available - $length - $gap);

                if ($cut > 0) {
                    $line .= str_repeat(Braille::BLANK, $gap).substr($word, $start, $cut * self::BYTES);
                    $start += $cut * self::BYTES;
                } elseif (0 === $length) {
                    $cut = self::cut($word, $start, $available - 1);
                    $line = substr($word, $start, $cut * self::BYTES).Braille::HYPHEN;
                    $start += $cut * self::BYTES;
                }

                yield $line;

                $available = $width(++$number);
                $line = '';
                $length = 0;
            }
        }

        if ($length > 0) {
            yield $line;
        }
    }

    /**
     * The lines some words take when every line holds `$width` cells.
     *
     * @param list<string> $words
     */
    public static function count(array $words, int $width): int
    {
        return iterator_count(self::lines($words, static fn (): int => $width));
    }

    /**
     * The cells from `$start` up to the last hyphen of the word that fits in the room, the hyphen included, with a cell
     * before it and one after it; 0 when there is none. Only the room is read, however long the word: the word is
     * longer than the room, so that a hyphen in the room always has a cell after it.
     */
    private static function afterHyphen(string $word, int $start, int $room): int
    {
        $offset = $start;
        $cut = 0;

        while (false !== ($found = strpos($word, Braille::HYPHEN, $offset))) {
            $position = intdiv($found - $start, self::BYTES);

            if ($position > $room - 1) {
                break;
            }

            if ($position > 0) {
                $cut = $position + 1;
            }

            $offset = $found + self::BYTES;
        }

        return $cut;
    }

    /**
     * Where to cut, from `$start`, a word longer than a line of `$room` cells and a hyphen: not after an announcing
     * cell, unless the whole room is made of them.
     */
    private static function cut(string $word, int $start, int $room): int
    {
        for ($cut = $room; $cut > 1; --$cut) {
            if (!\in_array(substr($word, $start + ($cut - 1) * self::BYTES, self::BYTES), self::INDICATORS, true)) {
                return $cut;
            }
        }

        return $room;
    }
}
