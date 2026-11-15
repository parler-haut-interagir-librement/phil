<?php

declare(strict_types=1);

namespace Phil\Passwords\Generator;

use Phil\Passwords\Exception\InvalidPolicy;
use Phil\Passwords\Generator\Internal\UniqueDecoding;
use Random\Engine\Secure;
use Random\Randomizer;

/**
 * Generates passphrases from a wordlist, each word drawn uniformly and independently from the cryptographically secure
 * source of the system: seven words of a list of a thousand give about 70 bits of entropy, and are easier to type and
 * remember than a dozen random characters.
 *
 * A policy is generated, and its entropy counted, only when the passphrase it writes gives back its draws: two
 * different draws that write the same passphrase would make it easier to guess than its entropy says. On the bundled
 * lists, every separator reads back; without one, the words read back with `Capitalization::Words` only, whose
 * capitals start each word.
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

    /**
     * @throws InvalidPolicy when two different draws of the policy can write the same passphrase
     */
    public function generate(PassphrasePolicy $policy = new PassphrasePolicy()): string
    {
        $this->assertDecodable($policy);

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
            $words[] = $capitalise ? Wordlist::capitalise($word) : $word;
        }

        if ($policy->includeDigit) {
            $words[$this->randomizer->getInt(0, $policy->words - 1)] .= $this->randomizer->getInt(0, 9);
        }

        return implode($policy->separator, $words);
    }

    /**
     * The entropy of a passphrase of the policy, in bits, for an attacker who knows the wordlist and the policy.
     * Capitalisation counts the forms it writes: a word it leaves as it is adds nothing.
     *
     * @throws InvalidPolicy when two different draws of the policy can write the same passphrase
     */
    public function entropyBits(PassphrasePolicy $policy = new PassphrasePolicy()): float
    {
        $this->assertDecodable($policy);

        $list = $this->wordlist;
        $words = match ($policy->capitalization) {
            Capitalization::None => $policy->words * $list->bitsPerWord(),
            Capitalization::First => $list->bitsPerCapitalisedWord() + ($policy->words - 1) * $list->bitsPerWord(),
            Capitalization::Words => $policy->words * $list->bitsPerCapitalisedWord(),
            Capitalization::Random => $policy->words * $list->bitsPerRandomlyCapitalisedWord(),
        };

        return $words + ($policy->includeDigit ? log(10 * $policy->words, 2) : 0);
    }

    /**
     * Whether every passphrase of the policy gives back its draws — its words as written and, with the digit, the word
     * that takes it and the digit —, so that `generate()` accepts the policy and `entropyBits()` counts it.
     */
    public function accepts(PassphrasePolicy $policy): bool
    {
        return null === $this->refusal($policy);
    }

    /**
     * @throws InvalidPolicy
     */
    private function assertDecodable(PassphrasePolicy $policy): void
    {
        $refusal = $this->refusal($policy);

        if (null !== $refusal) {
            throw new InvalidPolicy($refusal);
        }
    }

    /**
     * Why the passphrases of the policy do not give back their draws, or null when they do.
     */
    private function refusal(PassphrasePolicy $policy): ?string
    {
        $forms = $this->forms($policy->capitalization);

        if ($policy->includeDigit && $this->aDigitWritesAnotherForm($forms)) {
            return \sprintf('A digit appended to a word of the list "%s" can write another of its words, which would make the passphrase easier to guess than its entropy says: leave the digit out, or use a list whose words do not end with a digit.', $this->wordlist->name);
        }

        if (!$this->separates($forms, $policy)) {
            return \sprintf('With the separator "%s", two different draws from the list "%s" can write the same passphrase, which would make it easier to guess than its entropy says: choose a separator that none of its words holds, or capitalise every word.', $policy->separator, $this->wordlist->name);
        }

        return null;
    }

    /**
     * Every form a word of the passphrase can take: as it is, capitalised, or either.
     *
     * @return array<array-key, true> the forms, as keys: a numeric form is an integer key
     */
    private function forms(Capitalization $capitalization): array
    {
        $forms = [];

        foreach ($this->wordlist->words as $word) {
            if (Capitalization::Words !== $capitalization) {
                $forms[$word] = true;
            }

            if (Capitalization::None !== $capitalization) {
                $forms[Wordlist::capitalise($word)] = true;
            }
        }

        return $forms;
    }

    /**
     * Whether a form ending with a digit is also written by the form before that digit with the digit appended: a
     * passphrase holding it would not tell which word took the digit.
     *
     * @param array<array-key, true> $forms the forms, as keys: a numeric form is an integer key
     */
    private function aDigitWritesAnotherForm(array $forms): bool
    {
        foreach (array_keys($forms) as $form) {
            $form = (string) $form;

            if (ctype_digit(substr($form, -1)) && isset($forms[substr($form, 0, -1)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the words of a passphrase, the digit appended to one of them, and the separators can be read back in one
     * way only.
     *
     * @param array<array-key, true> $forms the forms, as keys: a numeric form is an integer key
     */
    private function separates(array $forms, PassphrasePolicy $policy): bool
    {
        $separator = $policy->separator;
        $written = implode('', array_keys($forms));
        // A digit that no word nor the separator holds marks the end of the word that takes it: the passphrase without
        // it reads back as the words alone do.
        $digitCodewords = $policy->includeDigit && false !== strpbrk($written.$separator, '0123456789');

        // The separator ends every word, and no word holds its bytes: the first of them closes the word.
        if ('' !== $separator && !$digitCodewords && false === strpbrk($written, $separator)) {
            return true;
        }

        $codewords = [];

        foreach (array_keys($forms) as $form) {
            $codewords[] = $form.$separator;

            if ($digitCodewords) {
                for ($digit = 0; $digit <= 9; ++$digit) {
                    $codewords[] = $form.$digit.$separator;
                }
            }
        }

        return UniqueDecoding::holds($codewords);
    }
}
