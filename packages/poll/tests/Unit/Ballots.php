<?php

declare(strict_types=1);

namespace Phil\Poll\Tests\Unit;

use Generator;
use Phil\Poll\Ballot;
use Phil\Poll\Result;
use Phil\Poll\Standing;

/**
 * Ballots written as a number of identical rankings, read once as a store streams them, and results described as
 * plain arrays.
 */
final class Ballots
{
    /**
     * @param list<array{int, list<string>}> $groups
     *
     * @return Generator<int, Ballot>
     */
    public static function of(array $groups): Generator
    {
        foreach ($groups as [$times, $choices]) {
            for ($i = 0; $i < $times; ++$i) {
                yield Ballot::of($choices);
            }
        }
    }

    /**
     * @return list<array{string, int, int}>
     */
    public static function standings(Result $result): array
    {
        return array_map(static fn (Standing $standing): array => [$standing->option, $standing->score, $standing->place], $result->standings);
    }
}
