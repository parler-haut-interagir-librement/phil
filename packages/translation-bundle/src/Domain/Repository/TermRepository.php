<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Domain\Repository;

use Phil\TranslationBundle\Domain\Model\TermRecord;
use Phil\TranslationMemory\LanguageTag;

/**
 * Where the terms of glossaries are kept.
 */
interface TermRepository
{
    /**
     * The terms of one pair of languages, in no particular order.
     *
     * @return list<TermRecord>
     */
    public function forLanguages(LanguageTag $sourceLanguage, LanguageTag $targetLanguage): array;

    public function get(LanguageTag $sourceLanguage, LanguageTag $targetLanguage, string $term): ?TermRecord;

    /**
     * Keeps a new term, or the changes made to one.
     */
    public function save(TermRecord $record): void;

    public function remove(TermRecord $record): void;
}
