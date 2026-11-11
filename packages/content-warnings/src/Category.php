<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

use Phil\ContentWarnings\Exception\InvalidCatalogue;

/**
 * A group of themes, by its key and its label: `violence`, `phobias`. A member's preference can cover a whole
 * category.
 */
final readonly class Category
{
    /**
     * @throws InvalidCatalogue for a malformed key or a label in no language
     */
    public function __construct(
        public string $key,
        public LocalizedText $label,
    ) {
        if (!Key::isValid($key)) {
            throw InvalidCatalogue::because(\sprintf('"%s" is not a category key', mb_substr($key, 0, 80)));
        }

        if ($label->isEmpty()) {
            throw InvalidCatalogue::because(\sprintf('the category "%s" has no label', $key));
        }
    }

    /**
     * @param list<string> $fallbacks
     */
    public function label(string $locale, array $fallbacks = ['en']): string
    {
        return $this->label->in($locale, $fallbacks);
    }
}
