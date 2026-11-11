<?php

declare(strict_types=1);

namespace Phil\Passwords\Generator;

use Phil\Passwords\Exception\InvalidWordlist;

/**
 * The words a passphrase draws from. The bundled lists, in English, French, Spanish and German, are common words of
 * three to ten lowercase ASCII letters, so that a passphrase is typed on any keyboard, written for this library; `of()`
 * takes the application's own.
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
     */
    private function __construct(
        public string $name,
        public array $words,
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

        foreach ($words as $word) {
            if ('' === $word || mb_strlen($word) > self::MAX_WORD_LENGTH || 1 === preg_match('/[\s\p{Z}]/u', $word)) {
                throw new InvalidWordlist(\sprintf('The words of the list "%s" have 1 to %d characters and no whitespace.', $name, self::MAX_WORD_LENGTH));
            }

            if (isset($seen[$word])) {
                throw new InvalidWordlist(\sprintf('The word "%s" is repeated in the list "%s".', $word, $name));
            }

            $seen[$word] = $word;
            $list[] = $word;
        }

        if (\count($list) < self::MIN_WORDS) {
            throw new InvalidWordlist(\sprintf('The list "%s" has %d words; a passphrase draws from at least %d.', $name, \count($list), self::MIN_WORDS));
        }

        return new self($name, $list);
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

        return new self($language, $words);
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
}
