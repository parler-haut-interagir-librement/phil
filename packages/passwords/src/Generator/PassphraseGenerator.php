<?php

declare(strict_types=1);

namespace Phil\Passwords\Generator;

use Random\Engine\Secure;
use Random\Randomizer;

/**
 * Generates passphrases from a wordlist, each word drawn uniformly and independently from the cryptographically secure
 * source of the system: seven words of a list of a thousand give about 70 bits of entropy, and are easier to type and
 * remember than a dozen random characters.
 *
 * The generator holds no state between two calls; a test gives it a seeded randomizer.
 */
final readonly class PassphraseGenerator
{
    private Wordlist $wordlist;

    /**
     * @param ?Wordlist $wordlist the words to draw from; the bundled English list when null
     */
    public function __construct(
        ?Wordlist $wordlist = null,
        private Randomizer $randomizer = new Randomizer(new Secure()),
    ) {
        $this->wordlist = $wordlist ?? Wordlist::english();
    }

    public function generate(PassphrasePolicy $policy = new PassphrasePolicy()): string
    {
        $words = [];
        $last = $this->wordlist->count() - 1;

        for ($i = 0; $i < $policy->words; ++$i) {
            $word = $this->wordlist->words[$this->randomizer->getInt(0, $last)];
            $capitalise = match ($policy->capitalization) {
                Capitalization::None => false,
                Capitalization::First => 0 === $i,
                Capitalization::Words => true,
                Capitalization::Random => 1 === $this->randomizer->getInt(0, 1),
            };
            $words[] = $capitalise ? mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1) : $word;
        }

        if ($policy->includeDigit) {
            $words[$this->randomizer->getInt(0, $policy->words - 1)] .= $this->randomizer->getInt(0, 9);
        }

        return implode($policy->separator, $words);
    }

    /**
     * The entropy of a passphrase of the policy, in bits, for an attacker who knows the wordlist and the policy.
     */
    public function entropyBits(PassphrasePolicy $policy = new PassphrasePolicy()): float
    {
        return $policy->words * $this->wordlist->bitsPerWord()
            + (Capitalization::Random === $policy->capitalization ? $policy->words : 0)
            + ($policy->includeDigit ? log(10 * $policy->words, 2) : 0);
    }
}
