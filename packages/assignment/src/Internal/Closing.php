<?php

declare(strict_types=1);

namespace Phil\Assignment\Internal;

use Phil\Assignment\Problem;

/**
 * The minimums: solve, close the open slot furthest under its minimum — the fewest seats taken, then the first
 * identifier —, solve again without it, until no slot runs under its minimum. An empty slot is not run and never
 * closed; a slot holding a forced applicant is never closed. At most one solve per slot, plus one.
 *
 * @internal
 */
final class Closing
{
    /**
     * @param callable(array<string, true>): Draft $solve
     *
     * @return array{Draft, list<string>} the last draft and the slots closed, in order
     */
    public static function solve(Problem $problem, callable $solve): array
    {
        $kept = [];

        foreach ($problem->forced as $pair) {
            $kept[$pair->slot] = true;
        }

        $closed = [];
        $order = [];

        while (true) {
            $draft = $solve($closed);
            $victim = null;
            $least = \PHP_INT_MAX;

            foreach ($problem->slots as $slot) {
                $id = $slot->id;
                $taken = $draft->taken($id);

                if (isset($closed[$id]) || isset($kept[$id]) || 0 === $taken || $taken >= $slot->minimum || $taken >= $least) {
                    continue;
                }

                $least = $taken;
                $victim = $id;
            }

            if (null === $victim) {
                return [$draft, $order];
            }

            $closed[$victim] = true;
            $order[] = $victim;
        }
    }
}
