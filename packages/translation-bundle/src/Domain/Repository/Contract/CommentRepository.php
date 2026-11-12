<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Domain\Repository\Contract;

use Phil\TranslationBundle\Domain\Model\CommentRecord;
use Phil\TranslationMemory\LanguageTag;

/**
 * Where the comments on segments are kept.
 */
interface CommentRepository
{
    /**
     * The comments on the segments of a document in one target language, oldest first.
     *
     * @return list<CommentRecord>
     */
    public function forDocument(string $document, LanguageTag $targetLanguage): array;

    public function get(int $id): ?CommentRecord;

    /**
     * Keeps a new comment, which receives its identifier.
     */
    public function save(CommentRecord $record): void;

    public function remove(CommentRecord $record): void;
}
