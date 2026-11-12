<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Fixture;

use Override;
use Phil\TranslationBundle\Domain\Model\SegmentRecord;
use Phil\TranslationBundle\Domain\Repository\SegmentRepository;
use Phil\TranslationBundle\Infrastructure\Memory\InMemorySegmentRepository;
use Phil\TranslationMemory\LanguageTag;

/**
 * The segments in memory, and the keys of every row saved, call by call — what a store that writes to a database is
 * asked to write.
 */
final class RecordingSegments implements SegmentRepository
{
    /**
     * @var list<list<string>>
     */
    public array $saved = [];

    private readonly InMemorySegmentRepository $segments;

    public function __construct()
    {
        $this->segments = new InMemorySegmentRepository();
    }

    #[Override]
    public function forDocument(string $document, LanguageTag $targetLanguage): array
    {
        return $this->segments->forDocument($document, $targetLanguage);
    }

    #[Override]
    public function save(SegmentRecord ...$records): void
    {
        $this->saved[] = array_values(array_map(static fn (SegmentRecord $record): string => $record->key(), $records));
        $this->segments->save(...$records);
    }

    #[Override]
    public function remove(SegmentRecord ...$records): void
    {
        $this->segments->remove(...$records);
    }
}
