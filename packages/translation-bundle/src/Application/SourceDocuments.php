<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Application;

/**
 * The application's documents to translate, found by the reference the translation screen is given.
 */
interface SourceDocuments
{
    /**
     * The document of a reference, `null` when there is none.
     */
    public function find(string $document): ?SourceDocument;
}
