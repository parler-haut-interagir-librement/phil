<?php

declare(strict_types=1);

namespace Phil\TranslationMemory;

use Phil\TranslationMemory\Exception\InvalidLanguage;

/**
 * A language as a BCP 47 tag — `en`, `fr-CA`, `zh-Hant-TW` —, written in its canonical case: the language in lower
 * case, a script of four letters in title case, a region of two letters in upper case, the other subtags in lower case.
 * Two tags are the same language when their canonical forms are equal.
 */
final readonly class LanguageTag
{
    private const string TAG = '/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/D';

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidLanguage when the tag is not a primary language of 2 to 8 letters followed by subtags of 1 to 8
     *                         letters or digits, joined by hyphens
     */
    public static function of(string $tag): self
    {
        if (1 !== preg_match(self::TAG, $tag)) {
            throw InvalidLanguage::tag($tag);
        }

        $subtags = explode('-', $tag);
        $canonical = [strtolower(array_shift($subtags))];

        foreach ($subtags as $subtag) {
            $canonical[] = match (true) {
                4 === \strlen($subtag) && ctype_alpha($subtag) => ucfirst(strtolower($subtag)),
                2 === \strlen($subtag) && ctype_alpha($subtag) => strtoupper($subtag),
                default => strtolower($subtag),
            };
        }

        return new self(implode('-', $canonical));
    }

    /**
     * The tag without its last subtag — `fr` for `fr-CA`, `zh-Hant` for `zh-Hant-TW` —, `null` for a primary language
     * alone.
     */
    public function parent(): ?self
    {
        $cut = strrpos($this->value, '-');

        return false === $cut ? null : new self(substr($this->value, 0, $cut));
    }

    /**
     * The tag of a locale as Symfony and ICU write it — `fr_CA` —, its underscores read as hyphens.
     *
     * @throws InvalidLanguage when the locale is not a tag once its underscores are hyphens
     */
    public static function fromLocale(string $locale): self
    {
        return self::of(strtr($locale, '_', '-'));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
