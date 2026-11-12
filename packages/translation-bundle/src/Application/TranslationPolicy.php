<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Application;

use Phil\TranslationMemory\LanguageTag;

/**
 * Who may do what on the translation screen, decided by the application for the current user.
 */
interface TranslationPolicy
{
    /**
     * Whether the current user may see the segments of a document in a language, write and propose targets, export
     * and import them.
     */
    public function canTranslate(string $document, LanguageTag $targetLanguage): bool;

    /**
     * Whether the current user may also mark targets as reviewed.
     */
    public function canReview(string $document, LanguageTag $targetLanguage): bool;

    /**
     * Whether the current user may add, change and remove the terms of the glossary of a pair of languages.
     */
    public function canEditGlossary(LanguageTag $sourceLanguage, LanguageTag $targetLanguage): bool;
}
