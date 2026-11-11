<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

use Phil\ContentWarnings\Exception\InvalidCatalogue;

/**
 * A text in several languages, by locale: `['en' => 'Spiders', 'fr' => 'Araignées']`. A locale is a language code of
 * two or three lowercase letters, optionally followed by subtags, `fr_CA`; a hyphen is read as an underscore.
 */
final readonly class LocalizedText
{
    public const int MAX_LENGTH = 500;

    /**
     * @param array<string, string> $texts
     */
    private function __construct(
        private array $texts,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<array-key, mixed> $texts by locale; each text is trimmed, an empty one is dropped
     *
     * @throws InvalidCatalogue for a malformed locale, a text that is no string or one over 500 characters
     */
    public static function of(array $texts): self
    {
        $checked = [];

        foreach ($texts as $locale => $text) {
            $normalized = str_replace('-', '_', (string) $locale);

            if (1 !== preg_match('/^[a-z]{2,3}(?:_[A-Za-z0-9]{2,8}){0,3}$/D', $normalized)) {
                throw InvalidCatalogue::because(\sprintf('"%s" is not a locale', mb_substr((string) $locale, 0, 40)));
            }

            if (!\is_string($text)) {
                throw InvalidCatalogue::because(\sprintf('the text in "%s" is not a string', $normalized));
            }

            $text = trim($text);

            if (mb_strlen($text) > self::MAX_LENGTH) {
                throw InvalidCatalogue::because(\sprintf('the text in "%s" is over %d characters', $normalized, self::MAX_LENGTH));
            }

            if ('' !== $text) {
                $checked[$normalized] = $text;
            }
        }

        return new self($checked);
    }

    /**
     * The text in the locale, else in its language, else in the first fallback that has one, else the first text;
     * empty when there is none.
     *
     * @param list<string> $fallbacks
     */
    public function in(string $locale, array $fallbacks = ['en']): string
    {
        $locale = str_replace('-', '_', $locale);
        $language = explode('_', $locale)[0];

        foreach ([$locale, $language, ...$fallbacks] as $candidate) {
            if (isset($this->texts[$candidate])) {
                return $this->texts[$candidate];
            }
        }

        return $this->texts[array_key_first($this->texts) ?? ''] ?? '';
    }

    public function has(string $locale): bool
    {
        return isset($this->texts[$locale]);
    }

    public function isEmpty(): bool
    {
        return [] === $this->texts;
    }

    /**
     * This text with the other's locales added, the other's text winning in a locale both have.
     */
    public function with(self $other): self
    {
        return new self([...$this->texts, ...$other->texts]);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->texts;
    }
}
