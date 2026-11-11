<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength\Internal;

use Closure;
use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;

/**
 * Finds the parts written twice or more in a row: from each position, the base that covers the longest span by its
 * repeats, the shortest base on a tie, so that `aaaa` is `a` four times and `abab` is `ab` twice. A base costs the
 * guesses of its own analysis, times its repeats.
 *
 * Each position tries each base length up to half of what remains, and each repeat stops at the first difference: the
 * work is bounded by the cube of the analysed length, which the estimator bounds.
 *
 * @internal
 */
final class RepeatMatcher
{
    /**
     * @param list<string>                 $characters
     * @param Closure(list<string>): float $baseGuesses the guesses of a base, by the estimator's own analysis
     *
     * @return list<PatternMatch>
     */
    public static function matches(array $characters, Closure $baseGuesses): array
    {
        $count = \count($characters);
        $matches = [];
        $i = 0;

        while ($i < $count - 1) {
            $repeat = self::longestRepeat($characters, $i);

            if (null === $repeat) {
                ++$i;

                continue;
            }

            [$length, $repeats] = $repeat;

            $base = \array_slice($characters, $i, $length);
            $end = $i + $length * $repeats - 1;
            $matches[] = new PatternMatch(
                Pattern::Repeat,
                $i,
                $end,
                implode('', \array_slice($characters, $i, $length * $repeats)),
                $baseGuesses($base) * $repeats,
                baseToken: implode('', $base),
                repeatCount: $repeats,
            );
            $i = $end + 1;
        }

        return $matches;
    }

    /**
     * @param list<string> $characters
     *
     * @return ?array{int, int} the length of the base and its repeats, null when nothing repeats from there
     */
    private static function longestRepeat(array $characters, int $start): ?array
    {
        $count = \count($characters);
        $best = null;

        for ($length = 1; $start + 2 * $length <= $count; ++$length) {
            $repeats = 1;

            while ($start + ($repeats + 1) * $length <= $count && self::equal($characters, $start, $start + $repeats * $length, $length)) {
                ++$repeats;
            }

            if ($repeats > 1 && (null === $best || $length * $repeats > $best[0] * $best[1])) {
                $best = [$length, $repeats];
            }
        }

        return $best;
    }

    /**
     * @param list<string> $characters
     */
    private static function equal(array $characters, int $first, int $second, int $length): bool
    {
        for ($k = 0; $k < $length; ++$k) {
            if ($characters[$first + $k] !== $characters[$second + $k]) {
                return false;
            }
        }

        return true;
    }
}
