<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Memory;

/**
 * How close two texts are: the Levenshtein distance between their characters — insertions, deletions and
 * substitutions of code points —, and the Dice coefficient of their character trigrams, a cheap first sort of many
 * candidates.
 *
 * When the two texts hold 256 different characters at most, each is written as one byte and PHP's own `levenshtein()`
 * counts the edits; otherwise the table is computed here, in a band around the diagonal as wide as the bound, and
 * abandoned as soon as a whole row is past the bound.
 *
 * The score of two texts is `1 - distance / length of the longer`, from 0 to 1.
 */
final class Similarity
{
    /**
     * The margin that keeps a score exactly at a bound from being lost to the rounding of floating-point numbers.
     */
    public const float EPSILON = 1e-9;

    private function __construct()
    {
    }

    /**
     * The score of two texts, `0.0` when it is below `$minimum`.
     */
    public static function score(string $a, string $b, float $minimum = 0.0): float
    {
        $left = mb_str_split($a);
        $right = mb_str_split($b);
        $longest = max(\count($left), \count($right));

        if (0 === $longest) {
            return 1.0;
        }

        $distance = self::distance($left, $right, (int) floor((1.0 - $minimum) * $longest + self::EPSILON));

        return null === $distance ? 0.0 : 1.0 - $distance / $longest;
    }

    /**
     * The Levenshtein distance between two lists of characters, `null` when it is larger than `$bound`.
     *
     * @param list<string> $a
     * @param list<string> $b
     */
    public static function distance(array $a, array $b, int $bound = \PHP_INT_MAX): ?int
    {
        $symbols = array_flip(array_values(array_unique([...$a, ...$b])));

        if (\count($symbols) > 256) {
            return self::banded($a, $b, $bound);
        }

        $distance = levenshtein(self::bytes($a, $symbols), self::bytes($b, $symbols));

        return $distance <= $bound ? $distance : null;
    }

    /**
     * The characters of a text, each written as the byte of its rank among the characters of both texts.
     *
     * @param list<string>       $characters
     * @param array<string, int> $symbols
     */
    private static function bytes(array $characters, array $symbols): string
    {
        $bytes = '';

        foreach ($characters as $character) {
            $bytes .= pack('C', $symbols[$character]);
        }

        return $bytes;
    }

    /**
     * The distance computed in a band around the diagonal, `null` past the bound.
     *
     * @param list<string> $a
     * @param list<string> $b
     */
    private static function banded(array $a, array $b, int $bound): ?int
    {
        $rows = \count($a);
        $columns = \count($b);
        $previous = range(0, $columns);

        for ($row = 1; $row <= $rows && min($previous) <= $bound; ++$row) {
            $current = [$row];

            for ($column = max(1, $row - $bound); $column <= min($columns, $row + $bound); ++$column) {
                $cell = $previous[$column - 1] + ($a[$row - 1] === $b[$column - 1] ? 0 : 1);

                if (isset($previous[$column])) {
                    $cell = min($cell, $previous[$column] + 1);
                }

                if (isset($current[$column - 1])) {
                    $cell = min($cell, $current[$column - 1] + 1);
                }

                $current[$column] = $cell;
            }

            $previous = $current;
        }

        $distance = $previous[$columns] ?? null;

        return null !== $distance && $distance <= $bound ? $distance : null;
    }

    /**
     * The character trigrams of a text, counted, the text padded with a space at both ends and read in lower case.
     *
     * @return array<string, int>
     */
    public static function trigrams(string $text): array
    {
        $characters = mb_str_split(' '.mb_strtolower($text).' ');
        $trigrams = [];

        for ($index = 0, $count = \count($characters) - 2; $index < $count; ++$index) {
            $trigram = $characters[$index].$characters[$index + 1].$characters[$index + 2];
            $trigrams[$trigram] = ($trigrams[$trigram] ?? 0) + 1;
        }

        return $trigrams;
    }

    /**
     * The Dice coefficient of two sets of counted trigrams: twice the shared ones over all of them, from 0 to 1.
     *
     * @param array<string, int> $a
     * @param array<string, int> $b
     */
    public static function dice(array $a, array $b): float
    {
        $total = array_sum($a) + array_sum($b);

        if (0 === $total) {
            return 1.0;
        }

        $shared = 0;

        foreach ($a as $trigram => $count) {
            $shared += min($count, $b[$trigram] ?? 0);
        }

        return 2 * $shared / $total;
    }
}
