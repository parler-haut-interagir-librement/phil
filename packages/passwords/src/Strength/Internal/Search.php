<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength\Internal;

use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;

/**
 * The cheapest way to cover a password with patterns, characters that follow none guessed one by one.
 *
 * An attacker who tries sequences of `l` patterns pays `l!` times the product of their guesses — the patterns may come
 * in any order —, plus SEQUENCE_PENALTY to the power `l - 1` for having tried every shorter sequence first. For each
 * end and each number of patterns, only the cheapest cover is kept, and a cover is dropped as soon as one with fewer
 * patterns costs no more: the work is bounded by the cube of the length, in practice far less. The runs guessed one by
 * one are only built as patterns for the cover chosen.
 *
 * @internal
 */
final readonly class Search
{
    public const float SEQUENCE_PENALTY = 10000.0;

    /**
     * The most patterns of a cover: one of more costs over SEQUENCE_PENALTY to the power MAX_PATTERNS - 1, far above
     * the best score, so that leaving it out changes no score and bounds the work.
     */
    public const int MAX_PATTERNS = 8;

    /**
     * The longest run guessed one by one after a pattern: a longer one costs over BRUTEFORCE_CARDINALITY to the power
     * MAX_RUN, far above the best score, so that leaving it out changes no score; the run of the whole password stays.
     */
    public const int MAX_RUN = 20;

    /**
     * @param list<string>       $characters
     * @param list<PatternMatch> $matches
     *
     * @return array{float, list<PatternMatch>} the guesses and the patterns of the cheapest cover
     */
    public static function cheapest(array $characters, array $matches): array
    {
        $count = \count($characters);

        if (0 === $count) {
            return [1.0, []];
        }

        $byEnd = [];

        foreach ($matches as $match) {
            $byEnd[$match->end][] = self::floor($match, $count);
        }

        $factorials = [1.0];

        for ($l = 1; $l <= self::MAX_PATTERNS; ++$l) {
            $factorials[$l] = $factorials[$l - 1] * $l;
        }

        /** @var array<int, array<int, array{float, float, int, ?PatternMatch}>> $best each end to each number of patterns: product, guesses, start of the last pattern, and the last pattern, null for a run guessed one by one */
        $best = [];
        // A run guessed one by one starts the password or follows a pattern: two runs in a row always cost more than
        // the one run they make together.
        $starts = [0];

        foreach (array_keys($characters) as $k) {
            foreach ($byEnd[$k] ?? [] as $match) {
                self::extend($best, $factorials, $match->start, $k, $match->guesses, $match);
            }

            foreach ($starts as $i) {
                if ($k - $i >= self::MAX_RUN && 0 !== $i) {
                    continue;
                }

                self::extend($best, $factorials, $i, $k, self::bruteforceGuesses($k - $i + 1), null);
            }

            if (array_any($best[$k], static fn (array $entry): bool => $entry[3] instanceof PatternMatch)) {
                $starts[] = $k + 1;
            }
        }

        // A cover is kept only when every cover with fewer patterns costs more: the one with the most patterns is the
        // cheapest.
        /** @var non-empty-list<int> $lengths every position is covered, by a run from the first character at least */
        $lengths = array_keys($best[$count - 1]);
        $length = max($lengths);
        $guesses = $best[$count - 1][$length][1];
        $sequence = [];

        for ($k = $count - 1; $k >= 0; --$length) {
            [, , $start, $match] = $best[$k][$length];
            array_unshift($sequence, $match ?? new PatternMatch(
                Pattern::Bruteforce,
                $start,
                $k,
                implode('', \array_slice($characters, $start, $k - $start + 1)),
                self::bruteforceGuesses($k - $start + 1),
            ));
            $k = $start - 1;
        }

        return [$guesses, $sequence];
    }

    /**
     * @param array<int, array<int, array{float, float, int, ?PatternMatch}>> $best
     * @param array<int, float>                                               $factorials
     */
    private static function extend(array &$best, array $factorials, int $start, int $end, float $matchGuesses, ?PatternMatch $match): void
    {
        $candidates = 0 === $start ? [0 => [1.0, $matchGuesses, $start, $match]] : $best[$start - 1];

        foreach ($candidates as $patterns => [$product]) {
            $length = $patterns + 1;

            if ($length > self::MAX_PATTERNS) {
                continue;
            }

            $product *= $matchGuesses;
            $guesses = $factorials[$length] * $product + self::SEQUENCE_PENALTY ** ($length - 1);

            foreach ($best[$end] ?? [] as $other => [, $otherGuesses]) {
                if ($other <= $length && $otherGuesses <= $guesses) {
                    continue 2;
                }

                if ($other > $length && $otherGuesses >= $guesses) {
                    unset($best[$end][$other]);
                }
            }

            $best[$end][$length] = [$product, $guesses, $start, $match];
        }
    }

    private static function bruteforceGuesses(int $length): float
    {
        return 1 === $length ? Guesses::MIN_SINGLE_CHARACTER + 1 : Guesses::BRUTEFORCE_CARDINALITY ** $length;
    }

    /**
     * A part of a longer password costs at least MIN_SINGLE_CHARACTER or MIN_SUBMATCH guesses.
     */
    private static function floor(PatternMatch $match, int $count): PatternMatch
    {
        $length = $match->length();

        if ($length === $count) {
            return $match;
        }

        return $match->withGuesses(max($match->guesses, 1 === $length ? Guesses::MIN_SINGLE_CHARACTER : Guesses::MIN_SUBMATCH));
    }
}
