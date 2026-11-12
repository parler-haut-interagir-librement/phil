<?php

declare(strict_types=1);

namespace Phil\SearchLoupe;

use FilesystemIterator;
use Loupe\Loupe\BrowseParameters;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\LoupeFactoryInterface;
use Loupe\Loupe\SearchParameters;
use Override;
use Phil\SearchBundle\Application\SearchIndex;
use Phil\SearchBundle\Domain\Exception\InvalidSearch;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\Index;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * The documents of `phil/search-bundle` in Loupe, an index in SQLite files and no server: one directory per index
 * under `$directory`, or memory without one. Only the text fields are searched, and a document holds every word
 * looked for; the filters are kept as lists of text — Loupe gives each field one type — and a list matches when one
 * of its values does, as the bundle's other adapters compare them. A query's list of values finds a document having
 * any of them; facets are Loupe's facet distribution, a facet whose filter is set counted by one more search without
 * that filter. Loupe has no synonyms: the alternatives of a query's terms are not read.
 *
 * Loupe declares the searched and the filtered fields of an index before it indexes: they are learned as documents
 * come and kept beside the index, in `phil_search.json`, with the name of the directory that holds Loupe's files.
 * The searched fields keep the order they came in, which Loupe ranks by: the first field a provider gives, a title,
 * outranks the next. The file is checked before each use — read again only when it changed —, so that a process sees
 * the fields and the rebuilds of another, and it is widened under a lock, so that no process writes back fewer fields
 * than another learned.
 *
 * A rebuild fills a new directory beside the live one, then points `phil_search.json` at it and deletes the old one:
 * the index answers searches all along. A document that brings a new field does the same on disk, with the documents
 * the index holds copied into the new directory under the lock: Loupe is never left to migrate its own files. The
 * writes hold the lock shared, so that none lands in a directory being copied.
 */
final class LoupeIndex implements SearchIndex
{
    public const string FIELDS_FILE = 'phil_search.json';

    private const string LOCK_FILE = 'phil_search.lock';

    private const string FIRST_DATA = 'loupe';

    /**
     * @var array<string, array{loupe: Loupe, searchable: list<string>, filterable: list<string>, data: string}>
     */
    private array $indexes = [];

    /**
     * Without a directory, the rows of each index by identifier, written again when Loupe is opened anew in memory.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $memory = [];

    /**
     * What `phil_search.json` held when each index read it, and the file's inode, size and modification time then.
     *
     * @var array<string, array{stat: list<int>, fields: array{searchable: list<string>, filterable: list<string>, data: string}}>
     */
    private array $read = [];

    public function __construct(
        private readonly LoupeFactoryInterface $factory,
        private readonly ?string $directory = null,
    ) {
    }

    #[Override]
    public function index(Document ...$documents): void
    {
        foreach ($this->byIndex($documents) as $index => $indexed) {
            [$searchable, $filterable] = $this->fieldsOf($indexed);
            // The fields are widened first, under the exclusive lock; the documents are then written under the shared
            // one, to the Loupe of the fields kept, which hold theirs: a write never takes the exclusive lock.
            $this->loupe($index, $searchable, $filterable);
            $this->writing($index, fn () => $this->loupe($index)->addDocuments(array_map($this->row(...), $indexed)));
            $this->remember($index, $indexed);
        }
    }

    #[Override]
    public function remove(string $index, array $ids): void
    {
        $this->writing(Index::check($index), fn () => $this->loupe($index)->deleteDocuments($ids));

        foreach ($ids as $id) {
            unset($this->memory[$index][$id]);
        }
    }

    #[Override]
    public function clear(string $index): void
    {
        $this->writing(Index::check($index), fn () => $this->loupe($index)->deleteAllDocuments());
        unset($this->memory[$index]);
    }

    #[Override]
    public function rebuild(string $index, iterable $batches): void
    {
        Index::check($index);

        if (null === $this->directory) {
            $this->rebuildInMemory($index, $batches);
        } else {
            $this->rebuildOnDisk($index, $batches);
        }
    }

    #[Override]
    public function search(string $index, SearchQuery $query): SearchResult
    {
        $loupe = $this->loupe(Index::check($index));
        $known = $loupe->getConfiguration()->getFilterableAttributes();

        if ([] !== array_diff(array_keys($query->filters), $known)) {
            return new SearchResult([], 0, array_fill_keys($query->facets, []));
        }

        $facets = array_values(array_intersect($query->facets, $known));
        $filtered = array_values(array_intersect($facets, array_keys($query->filters)));
        $plain = array_values(array_diff($facets, $filtered));
        $parameters = SearchParameters::create()
            ->withQuery($query->text)
            ->withFilter($this->filter($query->filters))
            ->withMatchingStrategy('all')
            ->withAttributesToRetrieve(['id'])
            ->withLimit($query->limit)
            ->withOffset($query->offset);
        $result = $loupe->search([] === $plain ? $parameters : $parameters->withFacets($plain));
        $distributions = [$result->getFacetDistribution()];

        // A facet whose filter is set is counted without that filter, by one more search with no hits.
        foreach ($filtered as $facet) {
            $distributions[] = $loupe->search(SearchParameters::create()
                ->withQuery($query->text)
                ->withFilter($this->filter($query->filtersWithout($facet)))
                ->withMatchingStrategy('all')
                ->withLimit(0)
                ->withFacets([$facet]))->getFacetDistribution();
        }

        $counts = array_fill_keys($query->facets, []);

        foreach ($distributions as $distribution) {
            foreach ($distribution as $facet => $values) {
                if (isset($counts[$facet])) {
                    $counts[$facet] = $values;
                }
            }
        }

        $ids = [];

        foreach ($result->getHits() as $hit) {
            $ids[] = \is_string($hit['id'] ?? null) ? $hit['id'] : '';
        }

        return new SearchResult($ids, $result->getTotalHits(), $counts);
    }

    /**
     * The filter expression of a query: each field equal to its value, or among its values, quoted as Loupe reads a
     * string.
     *
     * @param array<string, scalar|list<scalar>> $filters
     */
    private function filter(array $filters): string
    {
        $filter = [];

        foreach ($filters as $field => $value) {
            $quoted = array_map(static fn (bool|float|int|string $one): string => "'".str_replace("'", "''", (string) $one)."'", \is_array($value) ? $value : [$value]);
            $filter[] = \is_array($value) ? \sprintf('%s IN (%s)', $field, implode(', ', $quoted)) : \sprintf('%s = %s', $field, $quoted[0]);
        }

        return implode(' AND ', $filter);
    }

    /**
     * @param iterable<list<Document>> $batches
     */
    private function rebuildOnDisk(string $index, iterable $batches): void
    {
        $stored = $this->stored($index);
        $data = $this->newData();
        $fields = ['searchable' => $stored['searchable'], 'filterable' => $stored['filterable']];
        $loupe = null;

        try {
            foreach ($batches as $batch) {
                [$searchable, $filterable] = $this->fieldsOf($batch);
                $wider = $this->widen($fields, $searchable, $filterable);

                if (!$loupe instanceof Loupe || $wider !== $fields) {
                    $fields = $wider;
                    $loupe = $this->open($index, $data, $fields);
                }

                $loupe->addDocuments(array_map($this->row(...), $batch));
            }

            $this->lock($index, \LOCK_EX, function () use ($index, $data, &$fields, &$loupe): void {
                // Fields another process learned during the rebuild are kept.
                $now = $this->stored($index);
                $wider = $this->widen($fields, $now['searchable'], $now['filterable']);

                if (!$loupe instanceof Loupe || $wider !== $fields) {
                    $fields = $wider;
                    $loupe = $this->open($index, $data, $fields);
                }

                $this->write($index, [...$fields, 'data' => $data]);
                $this->indexes[$index] = ['loupe' => $loupe, ...$fields, 'data' => $data];

                $this->delete($index, $now['data']);
            });
        } catch (Throwable $throwable) {
            $this->delete($index, $data);

            throw $throwable;
        }
    }

    /**
     * The Loupe of an index, its fields widened to those given: opened again when another process changed them or
     * rebuilt the index. On disk, wider fields move the index to a new directory.
     *
     * @param list<string> $searchable
     * @param list<string> $filterable
     */
    private function loupe(string $index, array $searchable = [], array $filterable = []): Loupe
    {
        $open = $this->indexes[$index] ?? null;
        $stored = $this->stored($index);
        $wider = $this->widen($stored, $searchable, $filterable);

        if (null !== $open && $open['data'] === $stored['data'] && $wider['searchable'] === $open['searchable'] && $wider['filterable'] === $open['filterable']) {
            return $open['loupe'];
        }

        if (null !== $this->directory && ($wider['searchable'] !== $stored['searchable'] || $wider['filterable'] !== $stored['filterable'])) {
            $this->lock($index, \LOCK_EX, function () use ($index, $searchable, $filterable, &$stored, &$wider): void {
                $stored = $this->stored($index);
                $wider = $this->widen($stored, $searchable, $filterable);

                if ($wider['searchable'] !== $stored['searchable'] || $wider['filterable'] !== $stored['filterable']) {
                    $stored['data'] = $this->widenOnDisk($index, $stored, $wider);
                }
            });
        }

        return $this->openFilled($index, $stored['data'], $wider);
    }

    /**
     * Copies the documents of an index into a new directory of Loupe's files, with the wider fields, then switches to
     * it and deletes the old one; an index whose files do not exist yet keeps its directory. Returns the directory now
     * named in `phil_search.json`.
     *
     * Loupe would otherwise migrate its files itself, which fails once an index holds a hundred documents or more.
     *
     * @param array{searchable: list<string>, filterable: list<string>, data: string} $stored
     * @param array{searchable: list<string>, filterable: list<string>}               $wider
     */
    private function widenOnDisk(string $index, array $stored, array $wider): string
    {
        $data = $stored['data'];

        if (is_dir($this->directory.'/'.$index.'/'.$data)) {
            $data = $this->newData();

            try {
                $from = $this->open($index, $stored['data'], $stored);
                $to = $this->open($index, $data, $wider);

                // A page at a time, of the most Loupe gives.

                for ($offset = 0; [] !== ($hits = $from->browse(BrowseParameters::create()->withLimit(BrowseParameters::MAX_LIMIT)->withOffset($offset))->getHits()); $offset += BrowseParameters::MAX_LIMIT) {
                    $to->addDocuments(array_values($hits));
                }
            } catch (Throwable $throwable) {
                $this->delete($index, $data);

                throw $throwable;
            }
        }

        $this->write($index, [...$wider, 'data' => $data]);

        if ($data !== $stored['data']) {
            $this->delete($index, $stored['data']);
        }

        return $data;
    }

    /**
     * Opens the Loupe of an index and keeps it; in memory, it is filled with the rows the index holds.
     *
     * @param array{searchable: list<string>, filterable: list<string>} $fields
     */
    private function openFilled(string $index, string $data, array $fields): Loupe
    {
        $loupe = $this->open($index, $data, $fields);
        $this->indexes[$index] = ['loupe' => $loupe, ...$fields, 'data' => $data];

        if ([] !== ($this->memory[$index] ?? [])) {
            $loupe->addDocuments(array_values($this->memory[$index]));
        }

        return $loupe;
    }

    /**
     * @param iterable<list<Document>> $batches
     */
    private function rebuildInMemory(string $index, iterable $batches): void
    {
        $fields = $this->stored($index);
        $fields = ['searchable' => $fields['searchable'], 'filterable' => $fields['filterable']];

        $rows = [];

        foreach ($batches as $batch) {
            [$searchable, $filterable] = $this->fieldsOf($batch);
            $fields = $this->widen($fields, $searchable, $filterable);

            foreach ($batch as $document) {
                $rows[$document->id] = $this->row($document);
            }
        }

        $this->memory[$index] = $rows;
        $this->openFilled($index, self::FIRST_DATA, $fields);
    }

    /**
     * The name of a new directory of Loupe's files.
     */
    private function newData(): string
    {
        /** @var array{1: string} $hex */
        $hex = unpack('H*', random_bytes(6));

        return 'loupe-'.$hex[1];
    }

    /**
     * Runs a write to an index under the shared lock, on disk, so that it never lands in a directory being copied.
     *
     * @param callable(): void $write
     */
    private function writing(string $index, callable $write): void
    {
        if (null === $this->directory) {
            $write();

            return;
        }

        $this->lock($index, \LOCK_SH, $write);
    }

    /**
     * @param list<Document> $documents
     */
    private function remember(string $index, array $documents): void
    {
        if (null !== $this->directory) {
            return;
        }

        foreach ($documents as $document) {
            $this->memory[$index][$document->id] = $this->row($document);
        }
    }

    /**
     * @param array{searchable: list<string>, filterable: list<string>} $fields
     */
    private function open(string $index, string $data, array $fields): Loupe
    {
        $configuration = Configuration::create()
            ->withPrimaryKey('id')
            ->withSearchableAttributes($fields['searchable'])
            ->withFilterableAttributes($fields['filterable']);

        return null === $this->directory
            ? $this->factory->createInMemory($configuration)
            : $this->factory->create($this->directory.'/'.$index.'/'.$data, $configuration);
    }

    /**
     * The fields with those given added: the searched ones in the order they came, the filtered ones sorted.
     *
     * @param array{searchable: list<string>, filterable: list<string>} $fields
     * @param array<string>                                             $searchable
     * @param array<string>                                             $filterable
     *
     * @return array{searchable: list<string>, filterable: list<string>}
     */
    private function widen(array $fields, array $searchable, array $filterable): array
    {
        $filterable = array_unique([...$fields['filterable'], ...$filterable]);
        sort($filterable);

        return ['searchable' => array_values(array_unique([...$fields['searchable'], ...$searchable])), 'filterable' => $filterable];
    }

    /**
     * What is kept beside the index — none for an index never written —, read anew when the file changed: its
     * size, its modification time or its inode differ — it is replaced by a rename. A file changed within the current
     * second is read anyway, and not kept: a modification time counts seconds, and a file replaced twice within one
     * could otherwise look the same.
     *
     * @return array{searchable: list<string>, filterable: list<string>, data: string}
     */
    private function stored(string $index): array
    {
        if (null === $this->directory) {
            $open = $this->indexes[$index] ?? null;

            return ['searchable' => $open['searchable'] ?? [], 'filterable' => $open['filterable'] ?? [], 'data' => self::FIRST_DATA];
        }

        $file = $this->directory.'/'.$index.'/'.self::FIELDS_FILE;
        clearstatcache(true, $file);
        $info = @stat($file);
        $stat = false === $info ? [] : [$info['ino'], $info['size'], $info['mtime']];

        if ($stat === ($this->read[$index]['stat'] ?? null)) {
            return $this->read[$index]['fields'];
        }

        $fields = false === $info ? null : json_decode((string) @file_get_contents($file), true);
        $fields = \is_array($fields) ? $fields : [];

        $data = $fields['data'] ?? null;
        $read = [
            ...$this->widen(['searchable' => [], 'filterable' => []], $this->strings($fields['searchable'] ?? []), $this->strings($fields['filterable'] ?? [])),
            'data' => \is_string($data) && 1 === preg_match('/^loupe(-[0-9a-f]{12})?$/D', $data) ? $data : self::FIRST_DATA,
        ];

        if (false === $info || $info['mtime'] < time()) {
            $this->read[$index] = ['stat' => $stat, 'fields' => $read];
        }

        return $read;
    }

    /**
     * Writes what is kept beside the index at once: a reader sees the old file or the new one.
     *
     * @param array{searchable: list<string>, filterable: list<string>, data: string} $fields
     */
    private function write(string $index, array $fields): void
    {
        if (null === $this->directory) {
            return;
        }

        $file = $this->directory.'/'.$index.'/'.self::FIELDS_FILE;
        file_put_contents($file.'.tmp', json_encode($fields, \JSON_THROW_ON_ERROR));
        rename($file.'.tmp', $file);
    }

    /**
     * Runs a read and a write of the fields alone among the processes that share the directory, with `LOCK_EX`; with
     * `LOCK_SH`, a write of documents alongside others but never during a change of directory.
     *
     * @param int<1, 2>        $operation `LOCK_SH` or `LOCK_EX`
     * @param callable(): void $critical
     */
    private function lock(string $index, int $operation, callable $critical): void
    {
        $directory = $this->directory.'/'.$index;

        if (!@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw InvalidSearch::because(\sprintf('The directory of the index "%s" cannot be created.', $index));
        }

        $lock = fopen($directory.'/'.self::LOCK_FILE, 'c');

        if (false === $lock) {
            throw InvalidSearch::because(\sprintf('The index "%s" cannot be locked.', $index));
        }

        // The lock is released when $lock is freed: on return, or when the critical section throws.
        flock($lock, $operation);
        $critical();
    }

    /**
     * Deletes a directory of Loupe's files; another process that still has it open reads it until it opens the new one.
     */
    private function delete(string $index, string $data): void
    {
        $directory = $this->directory.'/'.$index.'/'.$data;

        if (null === $this->directory || !is_dir($directory)) {
            return;
        }

        /** @var iterable<SplFileInfo> $entries */
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($directory);
    }

    /**
     * @param array<Document> $documents
     *
     * @return array<string, list<Document>>
     */
    private function byIndex(array $documents): array
    {
        $byIndex = [];

        foreach ($documents as $document) {
            $byIndex[$document->index][] = $document;
        }

        return $byIndex;
    }

    /**
     * The fields searched and filtered in these documents, in the order they come, repeated as often as they come.
     *
     * @param list<Document> $documents
     *
     * @return array{list<string>, list<string>}
     */
    private function fieldsOf(array $documents): array
    {
        $searchable = [];
        $filterable = [];

        foreach ($documents as $document) {
            $searchable = [...$searchable, ...array_keys($document->text)];
            $filterable = [...$filterable, ...array_keys($document->filters)];
        }

        return [$searchable, $filterable];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Document $document): array
    {
        $filters = [];

        foreach ($document->filters as $field => $value) {
            $filters[$field] = array_map(strval(...), \is_array($value) ? $value : [$value]);
        }

        return ['id' => $document->id, ...$document->text, ...$filters];
    }

    /**
     * @return array<string>
     */
    private function strings(mixed $fields): array
    {
        return \is_array($fields) ? array_filter($fields, \is_string(...)) : [];
    }
}
