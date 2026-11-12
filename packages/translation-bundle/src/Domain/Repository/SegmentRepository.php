<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Domain\Repository;

use Phil\TranslationBundle\Domain\Model\SegmentRecord;
use Phil\TranslationMemory\LanguageTag;

/**
 * Where the segments of documents are kept.
 */
interface SegmentRepository
{
    /**
     * The segments of a document in one target language, by position.
     *
     * @return list<SegmentRecord>
     */
    public function forDocument(string $document, LanguageTag $targetLanguage): array;

    /**
     * Keeps new rows, and the changes made to rows, together.
     */
    public function save(SegmentRecord ...$records): void;

    /**
     * Removes rows, together.
     */
    public function remove(SegmentRecord ...$records): void;
}
