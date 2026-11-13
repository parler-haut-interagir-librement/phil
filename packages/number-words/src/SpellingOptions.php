<?php

declare(strict_types=1);

namespace Phil\NumberWords;

/**
 * The choices of a speller that the language leaves open.
 */
final readonly class SpellingOptions
{
    /**
     * @param FrenchSpelling $french      the hyphenation of French numbers
     * @param bool           $softHyphens keep the soft hyphens (U+00AD) ICU puts inside long German, Italian, Spanish
     *                                    and Dutch words, so a printed line may break there; left out by default
     * @param bool           $verbose     ICU's verbose rule sets, which say `and` after hundreds in English
     *                                    (« one hundred and twenty »); a language without them refuses the option
     */
    public function __construct(
        public FrenchSpelling $french = FrenchSpelling::Traditional,
        public bool $softHyphens = false,
        public bool $verbose = false,
    ) {
    }
}
