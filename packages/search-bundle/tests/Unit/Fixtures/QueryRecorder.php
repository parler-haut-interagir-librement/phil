<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Fixtures;

use Override;
use Phil\SearchBundle\Application\SearchIndex;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;

/**
 * An index that keeps the queries it is asked, and the synonyms it is given when it takes them.
 */
abstract class QueryRecorder implements SearchIndex
{
    /** @var list<SearchQuery> */
    public array $queries = [];

    /** @var list<array{string, array<string, list<string>>}> */
    public array $synonyms = [];

    #[Override]
    public function index(Document ...$documents): void
    {
    }

    #[Override]
    public function remove(string $index, array $ids): void
    {
    }

    #[Override]
    public function clear(string $index): void
    {
    }

    #[Override]
    public function rebuild(string $index, iterable $batches): void
    {
        foreach ($batches as $batch) {
            \assert([] !== $batch);
        }
    }

    #[Override]
    public function search(string $index, SearchQuery $query): SearchResult
    {
        $this->queries[] = $query;

        return new SearchResult([], 0);
    }
}
