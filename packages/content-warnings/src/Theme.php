<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

use Phil\ContentWarnings\Exception\InvalidCatalogue;

/**
 * A theme a piece of content can be warned for — `gore`, `spiders`, `suicide` — by its key, the key of its category,
 * its label and an optional description.
 */
final readonly class Theme
{
    public LocalizedText $description;

    /**
     * @throws InvalidCatalogue for a malformed key or category key, or a label in no language
     */
    public function __construct(
        public string $key,
        public string $category,
        public LocalizedText $label,
        ?LocalizedText $description = null,
    ) {
        if (!Key::isValid($key)) {
            throw InvalidCatalogue::because(\sprintf('"%s" is not a theme key', mb_substr($key, 0, 80)));
        }

        if (!Key::isValid($category)) {
            throw InvalidCatalogue::because(\sprintf('"%s" is not a category key', mb_substr($category, 0, 80)));
        }

        if ($label->isEmpty()) {
            throw InvalidCatalogue::because(\sprintf('the theme "%s" has no label', $key));
        }

        $this->description = $description ?? LocalizedText::empty();
    }

    /**
     * @param list<string> $fallbacks
     */
    public function label(string $locale, array $fallbacks = ['en']): string
    {
        return $this->label->in($locale, $fallbacks);
    }

    /**
     * @param list<string> $fallbacks
     */
    public function description(string $locale, array $fallbacks = ['en']): string
    {
        return $this->description->in($locale, $fallbacks);
    }
}
