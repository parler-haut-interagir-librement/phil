<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Memory;

use Override;
use Phil\TranslationBundle\Domain\Model\TermRecord;
use Phil\TranslationBundle\Domain\Repository\TermRepository;
use Phil\TranslationMemory\LanguageTag;

/**
 * The glossary terms in a PHP array, for tests and previews, in the order they were first kept.
 */
final class InMemoryTermRepository implements TermRepository
{
    /**
     * @var array<string, array<string, array<string, TermRecord>>> the terms by source language, target language and text
     */
    private array $records = [];

    #[Override]
    public function forLanguages(LanguageTag $sourceLanguage, LanguageTag $targetLanguage): array
    {
        return array_values($this->records[$sourceLanguage->value][$targetLanguage->value] ?? []);
    }

    #[Override]
    public function get(LanguageTag $sourceLanguage, LanguageTag $targetLanguage, string $term): ?TermRecord
    {
        return $this->records[$sourceLanguage->value][$targetLanguage->value][$term] ?? null;
    }

    #[Override]
    public function save(TermRecord $record): void
    {
        $this->records[$record->sourceLanguage()->value][$record->targetLanguage()->value][$record->source()] = $record;
    }

    #[Override]
    public function remove(TermRecord $record): void
    {
        unset($this->records[$record->sourceLanguage()->value][$record->targetLanguage()->value][$record->source()]);
    }
}
