<?php

declare(strict_types=1);

namespace Phil\Passwords\Generator;

use Phil\Passwords\Exception\InvalidWordlist;

/**
 * The words a passphrase draws from. The bundled lists, in English, French, Spanish and German, are common words of
 * three to ten lowercase ASCII letters, so that a passphrase is typed on any keyboard, written for this library; `of()`
 * takes the application's own.
 *
 * The entropy of a capitalised word is counted on the forms capitalisation writes, once per list: a word without case,
 * one already capitalised, or two words that differ only by the case of their first letter add less than a bit.
 */
final readonly class Wordlist
{
    /** The fewest words of a list: each word of a passphrase then adds at least 8 bits of entropy. */
    public const int MIN_WORDS = 256;

    public const int MAX_WORD_LENGTH = 32;

    /** The languages of the bundled lists, by their code. */
    public const array LANGUAGES = ['en', 'fr', 'es', 'de'];

    private const string DATA = __DIR__.'/../../data/wordlists/';

    /**
     * @param list<string> $words
     * @param float        $capitalisedBits         the entropy of a word drawn and capitalised, in bits
     * @param float        $randomlyCapitalisedBits the entropy of a word drawn and capitalised or not at random, in bits
     */
    private function __construct(
        public string $name,
        public array $words,
        private float $capitalisedBits,
        private float $randomlyCapitalisedBits,
    ) {
    }

    /**
     * @param iterable<string> $words
     *
     * @throws InvalidWordlist when the list has fewer than MIN_WORDS words, or a word is empty, longer than
     *                         MAX_WORD_LENGTH characters, repeated or holds whitespace
     */
    public static function of(string $name, iterable $words): self
    {
        $list = [];
        $seen = [];
        /** @var array<array-key, int> $capitalised how many words write each form, capitalised */
        $capitalised = [];

        foreach ($words as $word) {
            if ('' === $word || mb_strlen($word) > self::MAX_WORD_LENGTH || 1 === preg_match('/[\s\p{Z}]/u', $word)) {
                throw new InvalidWordlist(\sprintf('The words of the list "%s" have 1 to %d characters and no whitespace.', $name, self::MAX_WORD_LENGTH));
            }

            if (isset($seen[$word])) {
                throw new InvalidWordlist(\sprintf('The word "%s" is repeated in the list "%s".', $word, $name));
            }

            $seen[$word] = $word;
            $list[] = $word;

            $form = self::capitalise($word);
            $capitalised[$form] = ($capitalised[$form] ?? 0) + 1;
        }

        $count = \count($list);
        if ($count < self::MIN_WORDS) {
            throw new InvalidWordlist(\sprintf('The list "%s" has %d words; a passphrase draws from at least %d.', $name, $count, self::MIN_WORDS));
        }

        // At random, a form is also written by the word it already is, left as it is; a form no capitalisation writes
        // comes from one draw only, and loses nothing.
        $random = [];
        foreach ($capitalised as $form => $writers) {
            $random[] = isset($seen[$form]) ? $writers + 1 : $writers;
        }

        $bits = log($count, 2);

        return new self($name, $list, $bits - self::sharedBits($capitalised) / $count, $bits + 1.0 - self::sharedBits($random) / (2 * $count));
    }

    /**
     * A bundled list, by the code of its language.
     *
     * @throws InvalidWordlist when no list is bundled for that language
     */
    public static function bundled(string $language): self
    {
        if (!\in_array($language, self::LANGUAGES, true)) {
            throw new InvalidWordlist(\sprintf('No wordlist is bundled for the language "%s".', $language));
        }

        /** @var list<string> $words */
        $words = require self::DATA.$language.'.php';
        $bits = log(\count($words), 2);

        // Lowercase ASCII words, each once: capitalised, each gives a form of its own, which the tests check.
        return new self($language, $words, $bits, $bits + 1.0);
    }

    public static function english(): self
    {
        return self::bundled('en');
    }

    public static function french(): self
    {
        return self::bundled('fr');
    }

    public static function spanish(): self
    {
        return self::bundled('es');
    }

    public static function german(): self
    {
        return self::bundled('de');
    }

    public function count(): int
    {
        return \count($this->words);
    }

    /**
     * The entropy a word drawn from the list adds, in bits.
     */
    public function bitsPerWord(): float
    {
        return log(\count($this->words), 2);
    }

    /**
     * The entropy a word drawn from the list and capitalised adds, in bits: less than `bitsPerWord()` when two words of
     * the list differ only by the case of their first letter.
     */
    public function bitsPerCapitalisedWord(): float
    {
        return $this->capitalisedBits;
    }

    /**
     * The entropy a word drawn from the list and capitalised or not at random adds, in bits: one more than
     * `bitsPerWord()` when capitalisation gives each word a form of its own, less for the words it leaves as they are
     * and for the forms two words share.
     */
    public function bitsPerRandomlyCapitalisedWord(): float
    {
        return $this->randomlyCapitalisedBits;
    }

    /**
     * The word with its first letter in uppercase, as a passphrase writes it.
     */
    public static function capitalise(string $word): string
    {
        return mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1);
    }

    /**
     * What the equally likely draws lose to the forms they share, in bits times draws: the entropy of `n` draws is
     * `log2(n) − Σ c·log2(c) / n`, where each form is written by `c` of them.
     *
     * @param array<array-key, int> $draws how many draws write each form
     */
    private static function sharedBits(array $draws): float
    {
        $bits = 0.0;
        foreach ($draws as $count) {
            $bits += $count * log($count, 2);
        }

        return $bits;
    }
}
