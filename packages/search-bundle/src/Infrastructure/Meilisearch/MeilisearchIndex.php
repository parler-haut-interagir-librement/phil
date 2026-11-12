<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Meilisearch;

use Override;
use Phil\SearchBundle\Application\Contract\SynonymAwareIndex;
use Phil\SearchBundle\Application\SearchIndex;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\Index;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Documents in Meilisearch, through its HTTP API: each document's identifier is `id`, its text and filters its other
 * attributes. Meilisearch applies writes as tasks, a moment later.
 *
 * The filters are declared filterable as they come, added to those the index already has: the list is read from
 * Meilisearch, and written back, only when a document carries a filter the index does not know yet. A rebuild fills
 * the index `{index}__rebuild`, with the live index's settings, then swaps the two and deletes the old one.
 *
 * Facets are Meilisearch's facet distribution: a facet whose filter is set is counted by one more query without that
 * filter, all of them sent at once to `/multi-search`. Synonyms are Meilisearch's own, one list per index, which
 * `replaceSynonyms()` writes; Meilisearch adds synonyms and never replaces a word, so a one-way rule still finds the
 * word on its left.
 */
final class MeilisearchIndex implements SearchIndex, SynonymAwareIndex
{
    public const string REBUILD_SUFFIX = '__rebuild';

    /**
     * The filterable attributes known of each index, so that a write sends them only when they grow.
     *
     * @var array<string, list<string>>
     */
    private array $filterable = [];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $url,
        #[SensitiveParameter]
        private readonly ?string $key = null,
    ) {
    }

    #[Override]
    public function index(Document ...$documents): void
    {
        $byIndex = [];

        foreach ($documents as $document) {
            $byIndex[$document->index][] = $document;
        }

        foreach ($byIndex as $index => $indexed) {
            $this->write($index, $indexed);
        }
    }

    #[Override]
    public function remove(string $index, array $ids): void
    {
        $this->send('POST', '/indexes/'.Index::check($index).'/documents/delete-batch', $ids, 'a removal');
    }

    #[Override]
    public function clear(string $index): void
    {
        $this->send('DELETE', '/indexes/'.Index::check($index).'/documents', null, 'a clearing');
    }

    #[Override]
    public function rebuild(string $index, iterable $batches): void
    {
        $next = Index::check($index).self::REBUILD_SUFFIX;
        $this->send('DELETE', '/indexes/'.$next, null, 'the removal of a previous rebuild');
        $this->send('POST', '/indexes', ['uid' => $index, 'primaryKey' => 'id'], 'the creation of an index');
        $this->send('POST', '/indexes', ['uid' => $next, 'primaryKey' => 'id'], 'the creation of a rebuild');
        $settings = $this->read('/indexes/'.$index.'/settings', 'the settings');
        $this->filterable[$next] = [];

        if (null !== $settings) {
            $this->send('PATCH', '/indexes/'.$next.'/settings', $settings, 'the settings of a rebuild');
            $this->filterable[$next] = $this->names($settings['filterableAttributes'] ?? []);
        }

        try {
            foreach ($batches as $batch) {
                $this->write($next, $batch);
            }
        } catch (Throwable $throwable) {
            $this->send('DELETE', '/indexes/'.$next, null, 'the removal of a failed rebuild');
            unset($this->filterable[$next]);

            throw $throwable;
        }

        $this->send('POST', '/swap-indexes', [['indexes' => [$index, $next]]], 'a swap of indexes');
        $this->send('DELETE', '/indexes/'.$next, null, 'the removal of a rebuilt index');
        $this->filterable[$index] = $this->filterable[$next];
        unset($this->filterable[$next]);
    }

    #[Override]
    public function search(string $index, SearchQuery $query): SearchResult
    {
        $uid = Index::check($index);
        $main = ['q' => $query->text, 'filter' => $this->filter($query->filters), 'limit' => $query->limit, 'offset' => $query->offset, 'attributesToRetrieve' => ['id']];
        $filtered = array_intersect($query->facets, array_keys($query->filters));
        $plain = array_values(array_diff($query->facets, $filtered));

        if ([] !== $plain) {
            $main['facets'] = $plain;
        }

        if ([] === $filtered) {
            $answers = [$this->send('POST', '/indexes/'.$uid.'/search', $main, 'a search')];
        } else {
            // A facet whose filter is set is counted without that filter: one more query each, sent together.
            $queries = [['indexUid' => $uid, ...$main]];

            foreach ($filtered as $facet) {
                $queries[] = ['indexUid' => $uid, 'q' => $query->text, 'filter' => $this->filter($query->filtersWithout($facet)), 'limit' => 0, 'facets' => [$facet]];
            }

            $results = $this->send('POST', '/multi-search', ['queries' => $queries], 'a search')['results'] ?? [];
            $answers = array_values(array_filter(\is_array($results) ? $results : [], is_array(...)));
        }

        $answer = $answers[0] ?? [];
        $ids = [];

        foreach (\is_array($answer['hits'] ?? null) ? $answer['hits'] : [] as $hit) {
            $ids[] = \is_array($hit) && \is_scalar($hit['id'] ?? null) ? (string) $hit['id'] : '';
        }

        $facets = array_fill_keys($query->facets, []);

        foreach ($answers as $one) {
            foreach (\is_array($one['facetDistribution'] ?? null) ? $one['facetDistribution'] : [] as $facet => $counts) {
                if (isset($facets[$facet]) && \is_array($counts)) {
                    $facets[$facet] = array_filter($counts, is_int(...));
                }
            }
        }

        $total = $answer['estimatedTotalHits'] ?? 0;

        return new SearchResult($ids, \is_int($total) ? $total : 0, $facets);
    }

    #[Override]
    public function replaceSynonyms(string $index, array $synonyms): void
    {
        $this->send('PUT', '/indexes/'.Index::check($index).'/settings/synonyms', (object) $synonyms, 'the synonyms');
    }

    /**
     * The filter expression of a query: each field equal to its value, or among its values. Meilisearch's filters
     * unescape a quote and nothing else: a value goes as it is, its quotes escaped.
     *
     * @param array<string, scalar|list<scalar>> $filters
     *
     * @return list<string>
     */
    private function filter(array $filters): array
    {
        $filter = [];

        foreach ($filters as $field => $value) {
            $quoted = array_map(static fn (bool|float|int|string $one): string => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $one).'"', \is_array($value) ? $value : [$value]);
            $filter[] = \is_array($value) ? \sprintf('%s IN [%s]', $field, implode(', ', $quoted)) : \sprintf('%s = %s', $field, $quoted[0]);
        }

        return $filter;
    }

    /**
     * @param list<Document> $documents
     */
    private function write(string $uid, array $documents): void
    {
        $filters = [];

        foreach ($documents as $document) {
            $filters = [...$filters, ...array_keys($document->filters)];
        }

        $this->declareFilterable($uid, $filters);
        $this->send('POST', '/indexes/'.$uid.'/documents?primaryKey=id', array_map(static fn (Document $document): array => ['id' => $document->id, ...$document->text, ...$document->filters], $documents), 'the documents');
    }

    /**
     * Adds attributes to an index's filterable ones, keeping those it has: the list is read, then written, only when
     * an attribute is new to this process.
     *
     * @param list<string> $attributes
     */
    private function declareFilterable(string $uid, array $attributes): void
    {
        $known = $this->filterable[$uid] ?? [];
        $wanted = array_unique([...$known, ...$attributes]);

        if ([] === array_diff($wanted, $known)) {
            return;
        }

        $current = $this->read('/indexes/'.$uid.'/settings/filterable-attributes', 'the filterable attributes');
        $entries = array_filter(\is_array($current) ? $current : [], static fn (mixed $entry): bool => \is_string($entry) || \is_array($entry));
        $names = $this->names($entries);
        $missing = array_diff($wanted, $names);

        if ([] !== $missing) {
            $this->send('PUT', '/indexes/'.$uid.'/settings/filterable-attributes', [...$entries, ...$missing], 'the filterable attributes');
        }

        $this->filterable[$uid] = [...$names, ...$missing];
    }

    /**
     * The attribute names a list of filterable attributes holds: names, or objects with `attributePatterns`.
     *
     * @return list<string>
     */
    private function names(mixed $entries): array
    {
        $names = [];

        foreach (\is_array($entries) ? $entries : [] as $entry) {
            $patterns = \is_array($entry) ? $entry['attributePatterns'] ?? [] : [$entry];

            foreach (\is_array($patterns) ? $patterns : [] as $name) {
                if (\is_string($name) && !\in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * What Meilisearch answers a `GET`, or `null` when there is no such index.
     *
     * @return array<array-key, mixed>|null
     */
    private function read(string $path, string $what): ?array
    {
        $response = $this->client->request('GET', rtrim($this->url, '/').$path, ['auth_bearer' => $this->key]);
        $status = $response->getStatusCode();

        if (404 === $status) {
            return null;
        }

        if ($status >= 400) {
            throw SearchFailed::answered($what, $status);
        }

        return $response->toArray(false);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function send(string $method, string $path, mixed $body, string $what): array
    {
        $response = $this->client->request($method, rtrim($this->url, '/').$path, [
            'auth_bearer' => $this->key,
            'json' => $body,
        ]);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            throw SearchFailed::answered($what, $status);
        }

        return $response->toArray(false);
    }
}
