<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

use Phil\ContentWarnings\Exception\InvalidWarning;
use Phil\ContentWarnings\Exception\UnreadableWarnings;

/**
 * A member's choices about warned content: a preference per theme, a preference per category, and the display of
 * every other warning — folded unless the member or the application chose otherwise. Immutable; kept as an array with
 * `toArray()` and `fromArray()`.
 *
 * For one warning, the preference of its theme wins over that of its category, which wins over the default; a
 * preference that does not apply at the warning's severity gives way to the next.
 */
final readonly class WarningPreferences
{
    public const int MAX_ENTRIES = 2200;

    /**
     * @param array<string, Preference> $themes
     * @param array<string, Preference> $categories
     */
    private function __construct(
        public Display $default,
        private array $themes,
        private array $categories,
    ) {
        if (\count($themes) + \count($categories) > self::MAX_ENTRIES) {
            throw InvalidWarning::because(\sprintf('preferences have at most %d entries', self::MAX_ENTRIES));
        }
    }

    public static function defaults(Display $default = Display::Fold): self
    {
        return new self($default, [], []);
    }

    public function withDefault(Display $default): self
    {
        return new self($default, $this->themes, $this->categories);
    }

    /**
     * @throws InvalidWarning for a malformed key
     */
    public function withTheme(string $theme, Display $display, ?Severity $atLeast = null): self
    {
        return new self($this->default, [...$this->themes, self::key($theme) => new Preference($display, $atLeast)], $this->categories);
    }

    public function withoutTheme(string $theme): self
    {
        $themes = $this->themes;
        unset($themes[$theme]);

        return new self($this->default, $themes, $this->categories);
    }

    /**
     * @throws InvalidWarning for a malformed key
     */
    public function withCategory(string $category, Display $display, ?Severity $atLeast = null): self
    {
        return new self($this->default, $this->themes, [...$this->categories, self::key($category) => new Preference($display, $atLeast)]);
    }

    public function withoutCategory(string $category): self
    {
        $categories = $this->categories;
        unset($categories[$category]);

        return new self($this->default, $this->themes, $categories);
    }

    public function theme(string $theme): ?Preference
    {
        return $this->themes[$theme] ?? null;
    }

    public function category(string $category): ?Preference
    {
        return $this->categories[$category] ?? null;
    }

    /**
     * @return array<string, Preference> by theme key
     */
    public function themes(): array
    {
        return $this->themes;
    }

    /**
     * @return array<string, Preference> by category key
     */
    public function categories(): array
    {
        return $this->categories;
    }

    /**
     * The keys of the themes the member chose this display for, whatever the severity.
     *
     * @return list<string>
     */
    public function themesWith(Display $display): array
    {
        return array_map(strval(...), array_keys(array_filter($this->themes, static fn (Preference $preference): bool => $preference->display === $display)));
    }

    /**
     * The display of one warning, its theme read in the catalogue for its category; a theme the catalogue does not
     * have answers to its own preference and to the default only.
     */
    public function displayFor(ContentWarning $warning, ?Catalogue $catalogue = null): Display
    {
        $category = $catalogue?->theme($warning->theme)?->category;

        foreach ([$this->themes[$warning->theme] ?? null, null === $category ? null : $this->categories[$category] ?? null] as $preference) {
            if (null !== $preference && $preference->appliesTo($warning)) {
                return $preference->display;
            }
        }

        return $this->default;
    }

    /**
     * @return array{default: string, themes: array<string, array{display: string, at_least?: string}>, categories: array<string, array{display: string, at_least?: string}>}
     */
    public function toArray(): array
    {
        return [
            'default' => $this->default->value,
            'themes' => array_map($this->preferenceToArray(...), $this->themes),
            'categories' => array_map($this->preferenceToArray(...), $this->categories),
        ];
    }

    /**
     * Reads back what `toArray()` wrote.
     *
     * @param array<array-key, mixed> $stored
     *
     * @throws UnreadableWarnings for anything `toArray()` does not write
     */
    public static function fromArray(array $stored): self
    {
        if ([] !== array_diff(array_keys($stored), ['default', 'themes', 'categories'])) {
            throw UnreadableWarnings::because('preferences are an array with "default", "themes" and "categories"');
        }

        $default = Display::tryFrom(\is_string($stored['default'] ?? null) ? $stored['default'] : '') ?? throw UnreadableWarnings::because('the default display is "show", "fold" or "hide"');

        try {
            return new self($default, self::preferencesFromArray($stored['themes'] ?? []), self::preferencesFromArray($stored['categories'] ?? []));
        } catch (InvalidWarning $invalidWarning) {
            throw UnreadableWarnings::because($invalidWarning->getMessage());
        }
    }

    /**
     * @return array{display: string, at_least?: string}
     */
    private function preferenceToArray(Preference $preference): array
    {
        return $preference->atLeast instanceof Severity ? ['display' => $preference->display->value, 'at_least' => $preference->atLeast->value] : ['display' => $preference->display->value];
    }

    /**
     * @return array<string, Preference>
     */
    private static function preferencesFromArray(mixed $stored): array
    {
        if (!\is_array($stored)) {
            throw UnreadableWarnings::because('the preferences of themes and categories are arrays by key');
        }

        $preferences = [];

        foreach ($stored as $key => $entry) {
            if (!\is_array($entry) || [] !== array_diff(array_keys($entry), ['display', 'at_least'])) {
                throw UnreadableWarnings::because(\sprintf('the preference of "%s" is an array with "display" and an optional "at_least"', $key));
            }

            $display = Display::tryFrom(\is_string($entry['display'] ?? null) ? $entry['display'] : '');
            $atLeast = $entry['at_least'] ?? null;
            $severity = \is_string($atLeast) ? Severity::tryFrom($atLeast) : null;

            if (null === $display || (null !== $atLeast && null === $severity)) {
                throw UnreadableWarnings::because(\sprintf('the preference of "%s" has an unknown display or severity', $key));
            }

            $preferences[self::key((string) $key)] = new Preference($display, $severity);
        }

        return $preferences;
    }

    private static function key(string $key): string
    {
        if (!Key::isValid($key)) {
            throw InvalidWarning::because(\sprintf('"%s" is not a theme or category key', mb_substr($key, 0, 80)));
        }

        return $key;
    }
}
