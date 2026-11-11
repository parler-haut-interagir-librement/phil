<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength\Internal;

use Phil\Passwords\Strength\PatternMatch;

/**
 * Every pattern of a list of characters, and the cheapest cover of it; the repeats call it again on their base. It
 * keeps no reference to itself, so that nothing of an analysis outlives it.
 *
 * @internal
 */
final readonly class Analyser
{
    /**
     * @param list<DictionaryMatcher> $dictionaries
     */
    public function __construct(
        private array $dictionaries,
        private SpatialMatcher $spatial,
        private DateMatcher $dates,
    ) {
    }

    /**
     * @param list<string> $characters
     *
     * @return array{float, list<PatternMatch>}
     */
    public function analyse(array $characters): array
    {
        $matches = [];

        foreach ($this->dictionaries as $dictionary) {
            array_push($matches, ...$dictionary->matches($characters));
        }

        array_push(
            $matches,
            ...$this->spatial->matches($characters),
            ...SequenceMatcher::matches($characters),
            ...$this->dates->matches($characters),
            ...RepeatMatcher::matches($characters, fn (array $base): float => $this->analyse($base)[0]),
        );

        return Search::cheapest($characters, $matches);
    }
}
