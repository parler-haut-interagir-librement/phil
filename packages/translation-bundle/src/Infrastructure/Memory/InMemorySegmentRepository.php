<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Memory;

use Override;
use Phil\TranslationBundle\Domain\Model\SegmentRecord;
use Phil\TranslationBundle\Domain\Repository\SegmentRepository;
use Phil\TranslationMemory\LanguageTag;

/**
 * The segments in a PHP array, for tests and previews.
 */
final class InMemorySegmentRepository implements SegmentRepository
{
    /**
     * @var array<int, SegmentRecord>
     */
    private array $records = [];

    #[Override]
    public function forDocument(string $document, LanguageTag $targetLanguage): array
    {
        $records = array_filter($this->records, static fn (SegmentRecord $record): bool => $record->document() === $document && $record->targetLanguage()->equals($targetLanguage));
        usort($records, static fn (SegmentRecord $a, SegmentRecord $b): int => $a->position() <=> $b->position());

        return $records;
    }

    #[Override]
    public function save(SegmentRecord ...$records): void
    {
        foreach ($records as $record) {
            $this->records[spl_object_id($record)] = $record;
        }
    }

    #[Override]
    public function remove(SegmentRecord ...$records): void
    {
        foreach ($records as $record) {
            unset($this->records[spl_object_id($record)]);
        }
    }
}
