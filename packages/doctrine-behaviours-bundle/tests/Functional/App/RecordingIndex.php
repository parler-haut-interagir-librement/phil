<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional\App;

use LogicException;
use Override;
use Phil\SearchBundle\Application\SearchIndex;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;

/**
 * An index that keeps what it is told: the documents indexed and the identifiers removed, by index.
 */
final class RecordingIndex implements SearchIndex
{
    /**
     * @var array<string, array<string, Document>>
     */
    public array $documents = [];

    /**
     * @var list<array{string, list<string>}>
     */
    public array $removed = [];

    #[Override]
    public function index(Document ...$documents): void
    {
        foreach ($documents as $document) {
            $this->documents[$document->index][$document->id] = $document;
        }
    }

    #[Override]
    public function remove(string $index, array $ids): void
    {
        $this->removed[] = [$index, $ids];

        foreach ($ids as $id) {
            unset($this->documents[$index][$id]);
        }
    }

    #[Override]
    public function clear(string $index): void
    {
        $this->documents[$index] = [];
    }

    #[Override]
    public function rebuild(string $index, iterable $batches): void
    {
        throw new LogicException('Not rebuilt in these tests.');
    }

    #[Override]
    public function search(string $index, SearchQuery $query): SearchResult
    {
        throw new LogicException('Not searched in these tests.');
    }
}
