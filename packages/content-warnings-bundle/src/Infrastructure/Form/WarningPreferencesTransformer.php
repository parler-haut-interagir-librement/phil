<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Infrastructure\Form;

use Override;
use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Preference;
use Phil\ContentWarnings\Severity;
use Phil\ContentWarnings\WarningPreferences;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * `WarningPreferences` to the default, category and theme displays of the preferences form, and back. One instance
 * per form, which remembers the preferences it was given: what the form does not list is kept, and a display kept
 * keeps its severity.
 *
 * @phpstan-type FormData array{default?: Display|null, categories?: array<array-key, Display|null>, themes?: array<array-key, array<array-key, Display|null>>}
 *
 * @implements DataTransformerInterface<WarningPreferences, FormData>
 */
final class WarningPreferencesTransformer implements DataTransformerInterface
{
    private WarningPreferences $original;

    public function __construct(
        private readonly Catalogue $catalogue,
        Display $default = Display::Fold,
    ) {
        $this->original = WarningPreferences::defaults($default);
    }

    /**
     * @return array{default: Display, categories: array<string, Display|null>, themes: array<string, array<string, Display|null>>}
     */
    #[Override]
    public function transform(mixed $value): array
    {
        $this->original = $value ?? $this->original;
        $categories = [];
        $themes = [];

        foreach ($this->catalogue->categories() as $category) {
            $categories[$category->key] = $this->original->category($category->key)?->display;
            $themes[$category->key] = [];

            foreach ($this->catalogue->themesIn($category->key) as $theme) {
                $themes[$category->key][$theme->key] = $this->original->theme($theme->key)?->display;
            }
        }

        return ['default' => $this->original->default, 'categories' => $categories, 'themes' => $themes];
    }

    #[Override]
    public function reverseTransform(mixed $value): WarningPreferences
    {
        $preferences = $this->original->withDefault($value['default'] ?? $this->original->default);

        foreach ($value['categories'] ?? [] as $key => $display) {
            $key = (string) $key;
            $kept = $this->original->category($key);
            $preferences = $display instanceof Display
                ? $preferences->withCategory($key, $display, $this->severity($kept, $display))
                : $preferences->withoutCategory($key);
        }

        foreach ($value['themes'] ?? [] as $themes) {
            foreach ($themes as $key => $display) {
                $key = (string) $key;
                $kept = $this->original->theme($key);
                $preferences = $display instanceof Display
                    ? $preferences->withTheme($key, $display, $this->severity($kept, $display))
                    : $preferences->withoutTheme($key);
            }
        }

        return $preferences;
    }

    private function severity(?Preference $kept, Display $display): ?Severity
    {
        return $kept?->display === $display ? $kept->atLeast : null;
    }
}
