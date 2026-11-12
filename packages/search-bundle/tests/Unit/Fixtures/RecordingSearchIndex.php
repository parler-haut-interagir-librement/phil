<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Fixtures;

use Override;
use Phil\SearchBundle\Application\SearchIndex;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;

/**
 * A search index that records each write it receives, in order: the identifiers of the documents indexed by one call,
 * or the index and identifiers of one removal.
 */
final class RecordingSearchIndex implements SearchIndex
{
    /**
     * @var list<array{0: string, 1: list<string>, 2?: string}>
     */
    public array $calls = [];

    #[Override]
    public function index(Document ...$documents): void
    {
        $this->calls[] = ['index', array_map(static fn (Document $document): string => $document->index.':'.$document->id, array_values($documents))];
    }

    #[Override]
    public function remove(string $index, array $ids): void
    {
        $this->calls[] = ['remove', $ids, $index];
    }

    #[Override]
    public function clear(string $index): void
    {
    }

    #[Override]
    public function rebuild(string $index, iterable $batches): void
    {
    }

    #[Override]
    public function search(string $index, SearchQuery $query): SearchResult
    {
        return new SearchResult([], 0);
    }
}
