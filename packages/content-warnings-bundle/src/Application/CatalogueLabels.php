<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Application;

use Locale;
use Phil\ContentWarnings\Catalogue;

/**
 * The labels of the catalogue in the current locale — the default locale of `intl`, which Symfony sets to the
 * request's — or in the one given, then in the fallback locales.
 */
final readonly class CatalogueLabels
{
    /**
     * @param list<string> $fallbacks
     */
    public function __construct(
        private Catalogue $catalogue,
        private array $fallbacks = ['en'],
    ) {
    }

    /**
     * The label of a theme; its key for a theme the catalogue does not have.
     */
    public function theme(string $key, ?string $locale = null): string
    {
        return $this->catalogue->theme($key)?->label($locale ?? Locale::getDefault(), $this->fallbacks) ?? $key;
    }

    /**
     * The description of a theme, empty when it has none.
     */
    public function description(string $key, ?string $locale = null): string
    {
        return $this->catalogue->theme($key)?->description($locale ?? Locale::getDefault(), $this->fallbacks) ?? '';
    }

    /**
     * The label of a category; its key for a category the catalogue does not have.
     */
    public function category(string $key, ?string $locale = null): string
    {
        return $this->catalogue->category($key)?->label($locale ?? Locale::getDefault(), $this->fallbacks) ?? $key;
    }

    /**
     * The themes by category, as a choice field lists them: each category's label, then each theme's label and key.
     *
     * @return array<string, array<string, string>>
     */
    public function choices(?string $locale = null): array
    {
        $choices = [];

        foreach ($this->catalogue->categories() as $category) {
            $themes = [];

            foreach ($this->catalogue->themesIn($category->key) as $theme) {
                $themes[$this->theme($theme->key, $locale)] = $theme->key;
            }

            if ([] !== $themes) {
                $choices[$this->category($category->key, $locale)] = $themes;
            }
        }

        return $choices;
    }

    public function catalogue(): Catalogue
    {
        return $this->catalogue;
    }
}
