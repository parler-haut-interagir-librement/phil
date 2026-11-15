<?php

declare(strict_types=1);

namespace Phil\Passwords\Generator\Internal;

/**
 * Whether every string written by a sequence of codewords is written by that sequence only: the Sardinas–Patterson
 * test, on bytes.
 *
 * The test follows the dangling suffixes: what is left of a codeword once another codeword, or a dangling suffix, is
 * cut from its start, and what is left of a dangling suffix once a codeword is cut from its start. The code is
 * ambiguous as soon as a dangling suffix is itself a codeword. Every dangling suffix is a suffix of a codeword and is
 * followed once, so the work is bounded by the number of suffixes times the length of the longest codeword.
 *
 * @internal
 */
final readonly class UniqueDecoding
{
    /**
     * @param list<string> $codewords none empty
     */
    public static function holds(array $codewords): bool
    {
        if ([] === $codewords) {
            return true;
        }

        $set = [];

        foreach ($codewords as $codeword) {
            if (isset($set[$codeword])) {
                // Two codewords that are the same string.
                return false;
            }

            $set[$codeword] = true;
        }

        $shortest = min(array_map(strlen(...), $codewords));
        $pending = [];

        foreach ($codewords as $codeword) {
            array_push($pending, ...self::afterCodewords($codeword, $set, $shortest));
        }

        if ([] === $pending) {
            // A prefix code: no codeword starts another.
            return true;
        }

        $completions = self::completions($codewords);
        $seen = [];

        while ([] !== $pending) {
            $suffix = array_pop($pending);

            // A suffix as long as the string it was cut from would mean a codeword cut from itself.
            if ('' === $suffix || isset($set[$suffix])) {
                return false;
            }

            if (isset($seen[$suffix])) {
                continue;
            }

            $seen[$suffix] = true;
            array_push($pending, ...$completions[$suffix] ?? [], ...self::afterCodewords($suffix, $set, $shortest));
        }

        return true;
    }

    /**
     * What is left of the string once a codeword shorter than it is cut from its start, for each such codeword.
     *
     * @param array<array-key, true> $set
     *
     * @return list<string>
     */
    private static function afterCodewords(string $string, array $set, int $shortest): array
    {
        $left = [];

        for ($length = $shortest; $length < \strlen($string); ++$length) {
            if (isset($set[substr($string, 0, $length)])) {
                $left[] = substr($string, $length);
            }
        }

        return $left;
    }

    /**
     * Each proper prefix of a codeword, to what is left of each codeword it starts.
     *
     * @param list<string> $codewords
     *
     * @return array<array-key, list<string>>
     */
    private static function completions(array $codewords): array
    {
        $completions = [];

        foreach ($codewords as $codeword) {
            for ($length = 1; $length < \strlen($codeword); ++$length) {
                $completions[substr($codeword, 0, $length)][] = substr($codeword, $length);
            }
        }

        return $completions;
    }
}
