<?php

declare(strict_types=1);

namespace Phil\TranslationMemory;

use Phil\TranslationMemory\Exception\InvalidSegment;

/**
 * The fingerprint of a source text: the SHA-256 of the text with its white space normalized, in hexadecimal. Two
 * sources that differ only by white space have the same fingerprint; a change of a word, a punctuation mark or a tag
 * changes it, and makes the translations written for the old source stale.
 */
final readonly class Fingerprint
{
    private const string HEX = '/^[0-9a-f]{64}$/D';

    private function __construct(
        public string $value,
    ) {
    }

    public static function of(string $text): self
    {
        return new self(hash('sha256', Text::normalize($text)));
    }

    /**
     * A fingerprint read back from storage.
     *
     * @throws InvalidSegment when the value is not 64 lower-case hexadecimal digits
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::HEX, $value)) {
            throw InvalidSegment::because(\sprintf('"%s" is not a fingerprint: 64 lower-case hexadecimal digits are expected.', $value));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
