<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Check;

/**
 * A text in one language or several, by locale: the name of a template, of an axis, of a value.
 *
 * `in()` reads the text of a locale, else of its language, else the first one given.
 */
final readonly class Label
{
    /**
     * @param non-empty-array<string, string> $texts by locale
     */
    private function __construct(
        private array $texts,
    ) {
    }

    /**
     * @param array<array-key, string> $texts by locale (`fr`, `en`, `fr_CA`), at least one
     */
    public static function of(array $texts): self
    {
        if ([] === $texts) {
            throw InvalidDefinition::because('A label has its text in one locale at least.');
        }

        $checked = [];

        foreach ($texts as $locale => $text) {
            $locale = (string) $locale;

            if (1 !== preg_match('/^[a-z]{2,3}(?:_[A-Za-z0-9]{2,8})*$/D', $locale)) {
                throw InvalidDefinition::because(\sprintf('"%s" is not a locale: a language of 2 or 3 lowercase letters, then "_" and a region or script.', $locale));
            }

            $checked[$locale] = Check::text($text, \sprintf('The text of a label in %s', $locale));
        }

        return new self($checked);
    }

    /**
     * The text of the locale, else of its language (`fr` for `fr_CA`), else the first one given.
     */
    public function in(?string $locale = null): string
    {
        if (null !== $locale) {
            $language = explode('_', $locale)[0];

            return $this->texts[$locale] ?? $this->texts[$language] ?? $this->first();
        }

        return $this->first();
    }

    /**
     * @return non-empty-array<string, string> by locale, in the order given
     */
    public function texts(): array
    {
        return $this->texts;
    }

    private function first(): string
    {
        return array_first($this->texts);
    }
}
