<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Application;

use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;

/**
 * Where documents are indexed and searched: a table of the database, Meilisearch, or an engine of the application's.
 */
interface SearchIndex
{
    /**
     * Adds the documents, or replaces those with the same identifiers.
     */
    public function index(Document ...$documents): void;

    /**
     * @param list<string> $ids
     */
    public function remove(string $index, array $ids): void;

    public function clear(string $index): void;

    /**
     * Replaces every document of an index with those given, the index answering searches all along with the documents
     * it held until the last batch is written; a rebuild that fails leaves the index as it was.
     *
     * @param iterable<list<Document>> $batches
     */
    public function rebuild(string $index, iterable $batches): void;

    public function search(string $index, SearchQuery $query): SearchResult;
}
