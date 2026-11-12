<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Internal;

/**
 * The optimal string alignment distance between two ASCII strings: insertions, deletions, substitutions and swaps of
 * two neighbours each cost one. `gmial.com` is one swap from `gmail.com`.
 *
 * @internal
 */
final class Distance
{
    /**
     * The distance, or `$limit + 1` as soon as it exceeds `$limit`: a domain far from every known one costs little.
     *
     * Only the band of cells within `$limit` of the diagonal is computed, the others held at `$limit + 1`: a cell
     * that far off costs at least that much, and a path through it needs one more step to come back to an end the
     * lengths put within the limit, so it can no longer win.
     */
    public static function between(string $a, string $b, int $limit): int
    {
        $lengthA = \strlen($a);
        $lengthB = \strlen($b);
        $over = $limit + 1;

        // A swap costs one here and two to Levenshtein, so the Levenshtein distance is at most twice this one: past
        // twice the limit, which the native function tells for a fraction of the table below, this one exceeds it.
        if (abs($lengthA - $lengthB) > $limit || levenshtein($a, $b) > 2 * $limit) {
            return $over;
        }

        $row = array_fill(0, $lengthB + 1, $over);
        $before = [];
        $previous = $row;

        for ($j = min($limit, $lengthB); $j >= 0; --$j) {
            $previous[$j] = $j;
        }

        for ($i = 1; $i <= $lengthA; ++$i) {
            $current = $row;
            $current[0] = min($i, $over);
            $best = $current[0];
            $last = min($lengthB, $i + $limit);

            for ($j = max(1, $i - $limit); $j <= $last; ++$j) {
                $value = min(
                    $previous[$j] + 1,
                    $current[$j - 1] + 1,
                    $previous[$j - 1] + ($a[$i - 1] === $b[$j - 1] ? 0 : 1),
                );

                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $value = min($value, $before[$j - 2] + 1);
                }

                $current[$j] = $value;
                $best = min($best, $value);
            }

            if ($best > $limit) {
                return $over;
            }

            $before = $previous;
            $previous = $current;
        }

        return min($previous[$lengthB], $over);
    }
}
