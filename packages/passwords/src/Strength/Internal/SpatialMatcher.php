<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength\Internal;

use Phil\Passwords\Strength\KeyboardLayout;
use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;

/**
 * Finds the runs of three keys or more typed by walking from a key to its neighbour, on each layout, with their turns —
 * the changes of direction — and their shifted keys. One pass over the password for each layout.
 *
 * @internal
 */
final readonly class SpatialMatcher
{
    /**
     * @param list<KeyboardLayout> $layouts
     */
    public function __construct(
        private array $layouts,
    ) {
    }

    /**
     * @param list<string> $characters
     *
     * @return list<PatternMatch>
     */
    public function matches(array $characters): array
    {
        $matches = [];

        foreach ($this->layouts as $layout) {
            array_push($matches, ...$this->walk($layout, $characters));
        }

        return $matches;
    }

    /**
     * @param list<string> $characters
     *
     * @return list<PatternMatch>
     */
    private function walk(KeyboardLayout $layout, array $characters): array
    {
        $matches = [];
        $count = \count($characters);
        $i = 0;

        while ($i < $count - 2) {
            $j = $i;
            $direction = null;
            $turns = 0;
            $shifted = $layout->isShifted($characters[$i]) ? 1 : 0;

            while ($j + 1 < $count && null !== ($step = $layout->step($characters[$j], $characters[$j + 1]))) {
                if ($step[0] !== $direction) {
                    ++$turns;
                    $direction = $step[0];
                }

                $shifted += $step[1] ? 1 : 0;
                ++$j;
            }

            if ($j - $i >= 2) {
                $token = implode('', \array_slice($characters, $i, $j - $i + 1));
                $matches[] = new PatternMatch(
                    Pattern::Spatial,
                    $i,
                    $j,
                    $token,
                    $this->guesses($layout, $j - $i + 1, $turns, $shifted),
                    layout: $layout->name,
                    turns: $turns,
                    shifted: $shifted,
                );
            }

            $i = $j + 1;
        }

        return $matches;
    }

    /**
     * The walks of 2 to that many keys and of up to that many turns, from any key, in any direction at each turn,
     * then the variations of the shifted keys. A walk of `i` keys and `j` turns places its turns in C(i - 1, j - 1)
     * ways; over `i` from `j + 1` to the length, those sum to C(length, j) - 1.
     */
    private function guesses(KeyboardLayout $layout, int $length, int $turns, int $shifted): float
    {
        $guesses = 0.0;

        for ($j = 1; $j <= $turns; ++$j) {
            $guesses += (Guesses::binomial($length, $j) - 1) * $layout->keys * $layout->averageDegree ** $j;
        }

        return 0 === $shifted ? $guesses : $guesses * Guesses::variations($shifted, $length - $shifted);
    }
}
