<?php

declare(strict_types=1);

namespace Phil\Poll\Counting;

use Phil\Poll\Standing;

/**
 * The places of options sorted on a key, the lowest first: options with the same key share a place, and the next
 * key's place counts every option before it. Within a place, the higher score comes first, then the declared order.
 *
 * @internal
 */
final class Places
{
    /**
     * @param list<string>    $options the options, in their declared order
     * @param array<int, int> $scores  the score of each option, by its position
     * @param array<int, int> $keys    the sort key of each option, by its position, the lowest placed first
     *
     * @return list<Standing>
     */
    public static function of(array $options, array $scores, array $keys): array
    {
        $order = array_keys($options);
        usort($order, static fn (int $a, int $b): int => [$keys[$a], $scores[$b], $a] <=> [$keys[$b], $scores[$a], $b]);

        $standings = [];

        foreach ($order as $position => $index) {
            $before = $order[$position - 1] ?? null;
            $place = null !== $before && $keys[$before] === $keys[$index] ? $standings[$position - 1]->place : $position + 1;
            $standings[] = new Standing($options[$index], $scores[$index], $place);
        }

        return $standings;
    }

    /**
     * The places of scores, the highest first.
     *
     * @param list<string>    $options
     * @param array<int, int> $scores
     *
     * @return list<Standing>
     */
    public static function byScore(array $options, array $scores): array
    {
        return self::of($options, $scores, array_map(static fn (int $score): int => -$score, $scores));
    }
}
