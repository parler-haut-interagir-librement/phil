<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Segmentation;

/**
 * Which values of a UJO document are translated: the text fields whose name — or, in a list, the name of the list —
 * says they hold words, in the blocks of every type but those whose text is code.
 */
final readonly class TranslatableFields
{
    public const array NAMES = ['text', 'caption', 'title', 'message', 'content', 'description', 'name', 'term', 'subtitle', 'alt', 'label', 'summary'];

    public const array UNTRANSLATED_TYPES = ['code', 'mermaid', 'math', 'raw'];

    /**
     * @param list<string> $names             the names of the fields translated
     * @param list<string> $untranslatedTypes the block types none of whose fields are translated
     */
    public function __construct(
        public array $names = self::NAMES,
        public array $untranslatedTypes = self::UNTRANSLATED_TYPES,
    ) {
    }

    public function isTranslated(string $type): bool
    {
        return !\in_array($type, $this->untranslatedTypes, true);
    }

    public function holdsWords(?string $name): bool
    {
        return \in_array($name, $this->names, true);
    }
}
