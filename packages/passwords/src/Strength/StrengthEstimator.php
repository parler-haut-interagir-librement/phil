<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength;

use Phil\Passwords\Strength\Internal\Analyser;
use Phil\Passwords\Strength\Internal\DateMatcher;
use Phil\Passwords\Strength\Internal\DictionaryMatcher;
use Phil\Passwords\Strength\Internal\FeedbackBuilder;
use Phil\Passwords\Strength\Internal\SpatialMatcher;
use SensitiveParameter;

/**
 * Estimates how many guesses an attacker who knows how people choose passwords needs to find one: it finds every
 * pattern in the password — dictionary words, reversed or with substitutions, keyboard patterns, sequences, repeats,
 * dates and years —, takes the cheapest way to cover the password with them, and scores its guesses from 0 to 4.
 *
 * Only the first MAX_LENGTH characters are analysed: the work grows with the cube of the length at worst, and a
 * longer password only costs more guesses. A password that is not valid UTF-8 is read with its invalid bytes replaced.
 * The estimator holds no state between two calls: build it once and keep it.
 */
final readonly class StrengthEstimator
{
    public const int MAX_LENGTH = 128;

    /** The most user inputs kept: each becomes entries of a dictionary looked up in every analysis. */
    public const int MAX_USER_INPUTS = 32;

    private DictionaryMatcher $dictionary;

    private SpatialMatcher $spatial;

    /**
     * @param ?list<Dictionary>     $dictionaries  the dictionaries to look for words in; the bundled ones when null
     * @param ?list<KeyboardLayout> $layouts       the keyboards to look for patterns on; the bundled ones when null
     * @param ?int                  $referenceYear the year dates are measured from; the current year when null
     */
    public function __construct(
        ?array $dictionaries = null,
        ?array $layouts = null,
        private ?int $referenceYear = null,
    ) {
        $this->dictionary = DictionaryMatcher::of($dictionaries ?? Dictionary::bundled());
        $this->spatial = new SpatialMatcher($layouts ?? KeyboardLayout::bundled());
    }

    /**
     * @param list<string> $userInputs what the application knows of the person — name, email address, username —,
     *                                 which an attacker who targets them tries first
     */
    public function estimate(#[SensitiveParameter] string $password, array $userInputs = []): Strength
    {
        $characters = mb_str_split(mb_scrub($password, 'UTF-8'));
        $length = \count($characters);
        $characters = \array_slice($characters, 0, self::MAX_LENGTH);

        $inputs = DictionaryMatcher::of([Dictionary::ofUserInputs(\array_slice($userInputs, 0, self::MAX_USER_INPUTS))]);
        [$guesses, $sequence] = new Analyser([$this->dictionary, $inputs], $this->spatial, new DateMatcher($this->referenceYear ?? idate('Y')))->analyse($characters);
        $score = Score::fromGuesses($guesses);

        return new Strength(
            $guesses,
            $score,
            $sequence,
            CrackTimes::fromGuesses($guesses),
            FeedbackBuilder::build($score, $sequence),
            $length,
            \count($characters),
        );
    }
}
