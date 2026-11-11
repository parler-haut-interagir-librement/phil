<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

use JsonException;
use Phil\ContentWarnings\Exception\InvalidCatalogue;

/**
 * Builds a catalogue from a definition, or applies a definition onto a catalogue — the base list completed or
 * replaced by an application's own:
 *
 *     [
 *         'replace' => false,                    // true starts from an empty catalogue
 *         'categories' => ['venues' => ['label' => ['en' => 'Venues', 'fr' => 'Lieux']]],
 *         'themes' => [
 *             'crowds' => ['category' => 'venues', 'label' => ['en' => 'Crowds', 'fr' => 'Foules']],
 *             'gore' => ['label' => ['en' => 'Graphic gore']],   // an existing theme, its English label changed
 *         ],
 *         'remove' => ['clowns'],                // themes taken out
 *         'remove_categories' => [],             // categories taken out, once none of their themes is left
 *     ]
 *
 * A new category or theme comes after the existing ones; an existing one keeps its place. Its labels and description
 * are merged by locale, the definition winning; a theme's category is replaced when given. Each step is checked, and
 * the first error throws `InvalidCatalogue`: nothing of a refused definition is applied.
 */
final class CatalogueLoader
{
    public const int MAX_FILE_SIZE = 2_000_000;

    private const array DEFINITION_KEYS = ['replace', 'categories', 'themes', 'remove', 'remove_categories'];

    private const array CATEGORY_KEYS = ['label'];

    private const array THEME_KEYS = ['category', 'label', 'description'];

    /**
     * @param array<array-key, mixed> $definition
     *
     * @throws InvalidCatalogue
     */
    public function apply(array $definition, ?Catalogue $onto = null): Catalogue
    {
        $this->onlyKeys($definition, self::DEFINITION_KEYS, 'a definition');
        $replace = $definition['replace'] ?? false;

        if (!\is_bool($replace)) {
            throw InvalidCatalogue::because('"replace" is true or false');
        }

        $start = $replace ? null : $onto;
        $categories = $start?->categories() ?? [];
        $themes = $start?->themes() ?? [];

        foreach ($this->section($definition, 'categories') as $key => $entry) {
            $key = (string) $key;
            $entry = $this->entry($entry, self::CATEGORY_KEYS, 'the category "'.$key.'"');
            $label = LocalizedText::of($this->section($entry, 'label'));
            $categories[$key] = new Category($key, isset($categories[$key]) ? $categories[$key]->label->with($label) : $label);
        }

        foreach ($this->section($definition, 'themes') as $key => $entry) {
            $key = (string) $key;
            $themes[$key] = $this->theme($key, $this->entry($entry, self::THEME_KEYS, 'the theme "'.$key.'"'), $themes[$key] ?? null);
        }

        foreach ($this->keys($definition, 'remove') as $key) {
            if (!isset($themes[$key])) {
                throw InvalidCatalogue::because(\sprintf('the theme "%s" to remove is not in the catalogue', $key));
            }

            unset($themes[$key]);
        }

        foreach ($this->keys($definition, 'remove_categories') as $key) {
            if (!isset($categories[$key])) {
                throw InvalidCatalogue::because(\sprintf('the category "%s" to remove is not in the catalogue', $key));
            }

            unset($categories[$key]);
        }

        return new Catalogue($categories, $themes);
    }

    /**
     * Applies the definition a PHP file returns, or a JSON file holds, as `apply()` does.
     *
     * @throws InvalidCatalogue for a missing or unreadable file, one over 2 MB, or a definition refused
     */
    public function applyFile(string $path, ?Catalogue $onto = null): Catalogue
    {
        $size = is_file($path) ? filesize($path) : false;

        if (false === $size || $size > self::MAX_FILE_SIZE) {
            throw InvalidCatalogue::because(\sprintf('"%s" is not a readable file of %d bytes or fewer', $path, self::MAX_FILE_SIZE));
        }

        $definition = str_ends_with($path, '.php') ? require $path : $this->json($path);

        if (!\is_array($definition)) {
            throw InvalidCatalogue::because(\sprintf('"%s" holds no definition', $path));
        }

        return $this->apply($definition, $onto);
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private function theme(string $key, array $entry, ?Theme $existing): Theme
    {
        $category = $entry['category'] ?? $existing?->category;

        if (!\is_string($category)) {
            throw InvalidCatalogue::because(\sprintf('the theme "%s" names no category', $key));
        }

        $label = LocalizedText::of($this->section($entry, 'label'));
        $description = LocalizedText::of($this->section($entry, 'description'));

        return new Theme(
            $key,
            $category,
            $existing?->label->with($label) ?? $label,
            $existing?->description->with($description) ?? $description,
        );
    }

    private function json(string $path): mixed
    {
        try {
            return json_decode((string) file_get_contents($path), true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidCatalogue::because(\sprintf('"%s" is not JSON: %s', $path, $jsonException->getMessage()));
        }
    }

    /**
     * @param array<array-key, mixed> $array
     * @param list<string>            $allowed
     */
    private function onlyKeys(array $array, array $allowed, string $what): void
    {
        $unknown = array_diff(array_keys($array), $allowed);

        if ([] !== $unknown) {
            throw InvalidCatalogue::because(\sprintf('%s has the unknown key "%s"', $what, implode('", "', $unknown)));
        }
    }

    /**
     * @param list<string> $allowed
     *
     * @return array<array-key, mixed>
     */
    private function entry(mixed $entry, array $allowed, string $what): array
    {
        if (!\is_array($entry)) {
            throw InvalidCatalogue::because($what.' is not an array');
        }

        $this->onlyKeys($entry, $allowed, $what);

        return $entry;
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    private function section(array $array, string $key): array
    {
        $section = $array[$key] ?? [];

        if (!\is_array($section)) {
            throw InvalidCatalogue::because(\sprintf('"%s" is not an array', $key));
        }

        return $section;
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return list<string>
     */
    private function keys(array $array, string $key): array
    {
        $keys = [];

        foreach ($this->section($array, $key) as $item) {
            if (!\is_string($item)) {
                throw InvalidCatalogue::because(\sprintf('"%s" lists keys only', $key));
            }

            $keys[] = $item;
        }

        return $keys;
    }
}
