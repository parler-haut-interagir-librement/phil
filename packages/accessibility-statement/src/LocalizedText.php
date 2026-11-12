<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

use Phil\AccessibilityCheck\Language\LanguageTag;
use Phil\AccessibilityStatement\Exception\InvalidStatement;

/**
 * A text written in one or more languages, keyed by language tag: `['en' => 'Captions are added within a week.',
 * 'fr' => 'Les sous-titres sont ajoutés dans la semaine.']`. Each text is plain, never HTML: the page escapes it.
 */
final readonly class LocalizedText
{
    public const int MAX_LENGTH = 10_000;

    public const int MAX_LOCALES = 32;

    /**
     * @var non-empty-array<string, string>
     */
    public array $texts;

    /**
     * @param array<array-key, mixed> $texts
     */
    public function __construct(array $texts)
    {
        if ([] === $texts || \count($texts) > self::MAX_LOCALES) {
            throw InvalidStatement::because('text_locales', \sprintf('A text is written in 1 to %d languages.', self::MAX_LOCALES));
        }

        $checked = [];

        foreach ($texts as $locale => $text) {
            $locale = (string) $locale;

            if (!LanguageTag::isWellFormed($locale)) {
                throw InvalidStatement::because('text_locale', \sprintf('"%s" is not a language tag.', $locale));
            }

            if (!\is_string($text) || '' === trim($text) || mb_strlen($text) > self::MAX_LENGTH) {
                throw InvalidStatement::because('text_value', \sprintf('The text in "%s" is a string of 1 to %d characters, not blank.', $locale, self::MAX_LENGTH));
            }

            $checked[$locale] = $text;
        }

        $this->texts = $checked;
    }

    /**
     * The same text in every language given.
     */
    public static function of(string $text, string $locale = 'en'): self
    {
        return new self([$locale => $text]);
    }

    /**
     * The text in `$locale`; else in its primary language (`fr` for `fr-CA`); else in `$fallback`; else the first.
     */
    public function in(string $locale, ?string $fallback = null): string
    {
        $primary = LanguageTag::primary($locale);

        return $this->texts[$locale] ?? $this->texts[$primary] ?? $this->texts[$fallback ?? $locale] ?? array_first($this->texts);
    }

    public function has(string $locale): bool
    {
        return isset($this->texts[$locale]);
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return array_keys($this->texts);
    }
}
