<?php

declare(strict_types=1);

namespace Phil\LocaleDimensions;

use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Stringable;

/**
 * A locale: a language, optionally narrowed to a region (`fr`, `fr_CA`).
 *
 * **One spelling only.** The language is two or three lowercase letters and the region two uppercase letters,
 * joined by an underscore, as Symfony and `intl` write it. Content, translations and caches find a row by equality
 * on this string, so `fr-CA`, `fr_ca` and `fr_CA` admitted side by side would be three rows for one language, and a
 * lookup would miss two of them. `toLanguageTag()` gives the BCP 47 form that `hreflang` and `Content-Language` read.
 *
 * Which locales exist is the application's decision; this type only refuses what cannot be a locale.
 */
final readonly class Locale implements Stringable
{
    private const string PATTERN = '/^(?<language>[a-z]{2,3})(?:_(?<region>[A-Z]{2}))?$/D';

    private function __construct(
        public string $value,
        public string $language,
        public ?string $region,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value, $matches)) {
            throw InvalidLocaleDimension::because(\sprintf('"%s" is not a locale of the form "fr" or "fr_CA".', $value));
        }

        return new self($value, $matches['language'], $matches['region'] ?? null);
    }

    /**
     * `fr` and `fr_CA` share a language; `fr_CA` and `en_CA` share only a region, which does not count.
     */
    public function sharesLanguageWith(self $other): bool
    {
        return $this->language === $other->language;
    }

    /**
     * The BCP 47 language tag, with a hyphen: `fr`, `fr-CA`. What `hreflang`, `lang` and `Content-Language` read.
     */
    public function toLanguageTag(): string
    {
        return null === $this->region ? $this->language : $this->language.'-'.$this->region;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
