<?php

declare(strict_types=1);

namespace Phil\LocaleDimensions;

use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;

/**
 * The ordered locales tried for one requested locale, the requested one first.
 *
 * **The chain is declared, never derived.** Stripping the region (`fr_CA → fr`) and then falling back to a default
 * looks general and is wrong as soon as two regional variants of a language coexist: a missing `fr` translation would
 * fall to the default language while the `fr_CA` one sits one row away. The application writes the chain for each
 * locale it serves; this type only keeps it well formed and walks it.
 *
 * A chain resolves what is served; it does not choose which locale was requested. That choice happens before.
 */
final readonly class FallbackChain
{
    /**
     * @param non-empty-list<Locale> $locales
     */
    private function __construct(
        public array $locales,
    ) {
    }

    /**
     * @param array<Locale> $locales the requested locale first, then each fallback in order; keys are dropped
     */
    public static function of(array $locales): self
    {
        if ([] === $locales) {
            throw InvalidLocaleDimension::because('A fallback chain holds at least the requested locale.');
        }

        $seen = [];

        foreach ($locales as $locale) {
            if (isset($seen[$locale->value])) {
                throw InvalidLocaleDimension::because(\sprintf('The locale "%s" appears twice in the fallback chain.', $locale->value));
            }

            $seen[$locale->value] = $locale;
        }

        return new self(array_values($locales));
    }

    public function requested(): Locale
    {
        return $this->locales[0];
    }

    /**
     * The first locale of the chain that has something to serve, or `null` when none has.
     *
     * Nothing catches the last step: a chain that runs out resolves to nothing, and the caller refuses,
     * rather than serving a locale nobody declared.
     *
     * @param iterable<Locale> $available the locales that have something to serve
     */
    public function resolve(iterable $available): ?Locale
    {
        $present = [];

        foreach ($available as $locale) {
            $present[$locale->value] = $locale;
        }

        foreach ($this->locales as $locale) {
            if (isset($present[$locale->value])) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Whether serving `$resolved` leaves the language of the requested locale. A change of region within one
     * language is a lesser fault than a change of language, and only the second is reported here, so the
     * application can count it.
     */
    public function leavesRequestedLanguage(Locale $resolved): bool
    {
        return !$this->requested()->sharesLanguageWith($resolved);
    }

    public function toString(): string
    {
        return implode(' → ', $this->locales);
    }
}
