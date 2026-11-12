<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Memory;

use Override;
use Phil\TranslationBundle\Domain\Model\CommentRecord;
use Phil\TranslationBundle\Domain\Repository\Contract\CommentRepository;
use Phil\TranslationMemory\LanguageTag;

/**
 * The comments in a PHP array, numbered from 1, for tests and previews.
 */
final class InMemoryCommentRepository implements CommentRepository
{
    /**
     * @var array<int, CommentRecord>
     */
    private array $records = [];

    private int $last = 0;

    #[Override]
    public function forDocument(string $document, LanguageTag $targetLanguage): array
    {
        return array_values(array_filter($this->records, static fn (CommentRecord $record): bool => $record->document() === $document && $record->targetLanguage()->equals($targetLanguage)));
    }

    #[Override]
    public function get(int $id): ?CommentRecord
    {
        return $this->records[$id] ?? null;
    }

    #[Override]
    public function save(CommentRecord $record): void
    {
        $id = ++$this->last;
        $record->number($id);
        $this->records[$id] = $record;
    }

    #[Override]
    public function remove(CommentRecord $record): void
    {
        $id = $record->id();

        if (null !== $id) {
            unset($this->records[$id]);
        }
    }
}
