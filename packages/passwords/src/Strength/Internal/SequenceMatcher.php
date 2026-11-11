<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength\Internal;

use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;

/**
 * Finds the runs of three characters or more whose code points follow each other at a steady step of 1 to 5, up or
 * down: `abc`, `9753`, `ZYX`. One pass over the password.
 *
 * @internal
 */
final class SequenceMatcher
{
    private const int MAX_STEP = 5;

    /** The first characters an attacker tries first. */
    private const array OBVIOUS_STARTS = ['a', 'A', 'z', 'Z', '0', '1', '9'];

    /**
     * @param list<string> $characters
     *
     * @return list<PatternMatch>
     */
    public static function matches(array $characters): array
    {
        $codes = array_map(mb_ord(...), $characters);
        $count = \count($codes);
        $matches = [];
        $i = 0;

        while ($i < $count - 2) {
            $step = $codes[$i + 1] - $codes[$i];
            $j = $i;

            while ($j + 1 < $count && $codes[$j + 1] - $codes[$j] === $step) {
                ++$j;
            }

            if ($j - $i >= 2 && 0 !== $step && abs($step) <= self::MAX_STEP) {
                $token = implode('', \array_slice($characters, $i, $j - $i + 1));
                $matches[] = new PatternMatch(Pattern::Sequence, $i, $j, $token, self::guesses($characters[$i], $j - $i + 1, $step), step: $step);
            }

            $i = $j;
        }

        return $matches;
    }

    private static function guesses(string $first, int $length, int $step): float
    {
        $base = match (true) {
            \in_array($first, self::OBVIOUS_STARTS, true) => 4.0,
            ctype_digit($first) => 10.0,
            default => 26.0,
        };

        return $base * ($step === abs($step) ? 1 : 2) * $length;
    }
}
