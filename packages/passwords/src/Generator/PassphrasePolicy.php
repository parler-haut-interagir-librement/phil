<?php

declare(strict_types=1);

namespace Phil\Passwords\Generator;

use Phil\Passwords\Exception\InvalidPolicy;

/**
 * What a generated passphrase is made of: its number of words, the separator between them, their capitalisation, and
 * whether one of them ends with a digit.
 */
final readonly class PassphrasePolicy
{
    public const int MIN_WORDS = 3;

    public const int MAX_WORDS = 64;

    public const int MAX_SEPARATOR_LENGTH = 8;

    /**
     * @throws InvalidPolicy when the number of words is out of bounds, or the separator is too long or holds a letter,
     *                       which would merge it with the words
     */
    public function __construct(
        public int $words = 7,
        public string $separator = '-',
        public Capitalization $capitalization = Capitalization::None,
        public bool $includeDigit = false,
    ) {
        if ($words < self::MIN_WORDS || $words > self::MAX_WORDS) {
            throw new InvalidPolicy(\sprintf('A generated passphrase has %d to %d words.', self::MIN_WORDS, self::MAX_WORDS));
        }

        if (\strlen($separator) > self::MAX_SEPARATOR_LENGTH || 1 === preg_match('/\p{L}/u', $separator)) {
            throw new InvalidPolicy(\sprintf('The separator of a passphrase has at most %d bytes and no letter.', self::MAX_SEPARATOR_LENGTH));
        }
    }

    public function withWords(int $words): self
    {
        return new self($words, $this->separator, $this->capitalization, $this->includeDigit);
    }

    public function withSeparator(string $separator): self
    {
        return new self($this->words, $separator, $this->capitalization, $this->includeDigit);
    }

    public function withCapitalization(Capitalization $capitalization): self
    {
        return new self($this->words, $this->separator, $capitalization, $this->includeDigit);
    }

    public function withDigit(bool $include = true): self
    {
        return new self($this->words, $this->separator, $this->capitalization, $include);
    }
}
