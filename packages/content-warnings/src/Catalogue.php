<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

use Phil\ContentWarnings\Exception\InvalidCatalogue;

/**
 * The themes content can be warned for, grouped in categories, in the order they were defined. Immutable:
 * `CatalogueLoader` builds one from a definition, or applies a definition onto one to give another.
 */
final readonly class Catalogue
{
    public const int MAX_CATEGORIES = 200;

    public const int MAX_THEMES = 2000;

    /**
     * @var array<string, Category>
     */
    private array $categories;

    /**
     * @var array<string, Theme>
     */
    private array $themes;

    /**
     * @param array<array-key, Category> $categories
     * @param array<array-key, Theme>    $themes
     *
     * @throws InvalidCatalogue for a key given twice, a theme in a category the catalogue does not have, or a
     *                          catalogue over its bounds
     */
    public function __construct(array $categories = [], array $themes = [])
    {
        if (\count($categories) > self::MAX_CATEGORIES || \count($themes) > self::MAX_THEMES) {
            throw InvalidCatalogue::because(\sprintf('a catalogue has at most %d categories and %d themes', self::MAX_CATEGORIES, self::MAX_THEMES));
        }

        $byKey = [];

        foreach ($categories as $category) {
            if (isset($byKey[$category->key])) {
                throw InvalidCatalogue::because(\sprintf('the category "%s" is given twice', $category->key));
            }

            $byKey[$category->key] = $category;
        }

        $this->categories = $byKey;
        $byKey = [];

        foreach ($themes as $theme) {
            if (isset($byKey[$theme->key])) {
                throw InvalidCatalogue::because(\sprintf('the theme "%s" is given twice', $theme->key));
            }

            if (!isset($this->categories[$theme->category])) {
                throw InvalidCatalogue::because(\sprintf('the theme "%s" is in the unknown category "%s"', $theme->key, $theme->category));
            }

            $byKey[$theme->key] = $theme;
        }

        $this->themes = $byKey;
    }

    public function theme(string $key): ?Theme
    {
        return $this->themes[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->themes[$key]);
    }

    public function category(string $key): ?Category
    {
        return $this->categories[$key] ?? null;
    }

    /**
     * @return array<string, Theme> by key
     */
    public function themes(): array
    {
        return $this->themes;
    }

    /**
     * @return array<string, Category> by key
     */
    public function categories(): array
    {
        return $this->categories;
    }

    /**
     * @return array<string, Theme> the themes of the category, by key
     */
    public function themesIn(string $category): array
    {
        return array_filter($this->themes, static fn (Theme $theme): bool => $theme->category === $category);
    }
}
