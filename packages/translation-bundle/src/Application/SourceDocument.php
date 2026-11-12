<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Application;

use Phil\TranslationMemory\LanguageTag;
use Phil\Ujo\Document;

/**
 * A document to translate, as the application gives it: its content in UJO, its language, and a title to show.
 */
final readonly class SourceDocument
{
    public function __construct(
        public Document $content,
        public LanguageTag $language,
        public string $title = '',
    ) {
    }
}
