<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\SearchBundle\Application\SearchIndex;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\Index;
use Phil\SearchBundle\Domain\ValueObject\Phrase;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;

/**
 * Documents in a table of the application's database, for a site whose search needs no engine: a document is found
 * when its words hold every term looked for — one of its alternatives, with synonyms —, and its filters every value
 * asked — one of them, for a list —; the documents where the words come most often first, then by identifier. The
 * values of each facet asked are counted in PHP, on the same rows. A search with words, filters or facets reads every
 * candidate row into PHP, where it is filtered and ranked: past a few thousand documents, use Meilisearch or Loupe. A
 * search with none of them reads one page of identifiers and counts the rest in SQL.
 *
 * The text is kept in lower case, every run of white space one space, so that a phrase is found across a line break.
 * A rebuild runs in one transaction: other connections see the documents the index held until it commits.
 */
final readonly class DbalSearchIndex implements SearchIndex
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function index(Document ...$documents): void
    {
        $this->connection->transactional(function () use ($documents): void {
            foreach ($documents as $document) {
                $this->connection->delete(SearchTable::NAME, ['index_name' => $document->index, 'document_id' => $document->id]);
                $this->connection->insert(SearchTable::NAME, [
                    'index_name' => $document->index,
                    'document_id' => $document->id,
                    'content' => Phrase::normalize(implode(' ', $document->text)),
                    'filters' => $document->filters,
                ], ['filters' => Types::JSON]);
            }
        });
    }

    #[Override]
    public function remove(string $index, array $ids): void
    {
        Index::check($index);

        // One statement; an empty list removes nothing.
        $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE index_name = ? AND document_id IN (?)', SearchTable::NAME),
            [$index, $ids],
            [ParameterType::STRING, ArrayParameterType::STRING],
        );
    }

    #[Override]
    public function clear(string $index): void
    {
        $this->connection->delete(SearchTable::NAME, ['index_name' => Index::check($index)]);
    }

    #[Override]
    public function rebuild(string $index, iterable $batches): void
    {
        // The name is checked by the clearing, before anything is read or written.
        $this->connection->transactional(function () use ($index, $batches): void {
            $this->clear($index);

            foreach ($batches as $batch) {
                $this->index(...$batch);
            }
        });
    }

    #[Override]
    public function search(string $index, SearchQuery $query): SearchResult
    {
        Index::check($index);
        $terms = $query->terms();

        if ([] === $terms && [] === $query->filters && [] === $query->facets) {
            return $this->page($index, $query);
        }

        $sql = \sprintf('SELECT document_id, %s, filters FROM %s WHERE index_name = ?', [] === $terms ? "'' AS content" : 'content', SearchTable::NAME);
        $parameters = [$index];

        foreach ($terms as $alternatives) {
            $sql .= ' AND ('.implode(' OR ', array_fill(0, \count($alternatives), "content LIKE ? ESCAPE '!'")).')';

            foreach ($alternatives as $alternative) {
                $parameters[] = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $alternative).'%';
            }
        }

        $found = [];
        $facets = array_fill_keys($query->facets, []);

        foreach ($this->connection->fetchAllAssociative($sql, $parameters) as $row) {
            $filters = $this->filters($row['filters']);
            $missed = $this->missed($filters, $query->filters);
            $facets = $this->countFacets($facets, $filters, $missed);

            if ([] === $missed) {
                $found[] = ['score' => $this->score(\is_string($row['content']) ? $row['content'] : '', $terms), 'id' => \is_string($row['document_id']) ? $row['document_id'] : ''];
            }
        }

        usort($found, static function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            return strcmp($a['id'], $b['id']);
        });

        return new SearchResult(\array_slice(array_column($found, 'id'), $query->offset, $query->limit), \count($found), $facets);
    }

    /**
     * Every document of the index, by identifier: a page read and the rest counted in SQL.
     */
    private function page(string $index, SearchQuery $query): SearchResult
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('document_id')
            ->from(SearchTable::NAME)
            ->where('index_name = :index')
            ->orderBy('document_id')
            ->setParameter('index', $index)
            ->setFirstResult($query->offset)
            ->setMaxResults($query->limit)
            ->fetchFirstColumn();
        $total = $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE index_name = ?', SearchTable::NAME), [$index]);

        $ids = [];

        foreach ($rows as $id) {
            $ids[] = \is_string($id) ? $id : '';
        }

        // A count always comes back: a number, as text for some drivers.
        return new SearchResult($ids, (int) (is_numeric($total) ? $total : null));
    }

    /**
     * The fields of the filters asked that a document does not have as asked.
     *
     * @param array<array-key, mixed>            $filters
     * @param array<string, scalar|list<scalar>> $asked
     *
     * @return list<string>
     */
    private function missed(array $filters, array $asked): array
    {
        $missed = [];

        foreach ($asked as $field => $value) {
            // array_intersect() compares the values as text.
            $wanted = \is_array($value) ? $value : [$value];

            if ([] === array_intersect($wanted, $this->values($filters[$field] ?? []))) {
                $missed[] = $field;
            }
        }

        return $missed;
    }

    /**
     * How often the alternatives of the terms come in a text.
     *
     * @param list<list<string>> $terms
     */
    private function score(string $content, array $terms): int
    {
        $score = 0;

        foreach ($terms as $alternatives) {
            foreach ($alternatives as $alternative) {
                $score += substr_count($content, $alternative);
            }
        }

        return $score;
    }

    /**
     * The counts of each facet with the values of one more document: a facet counts the document when it misses no
     * filter but that facet's own.
     *
     * @param array<string, array<string, int>> $facets
     * @param array<array-key, mixed>           $filters
     * @param list<string>                      $missed
     *
     * @return array<string, array<string, int>>
     */
    private function countFacets(array $facets, array $filters, array $missed): array
    {
        foreach (array_keys($facets) as $facet) {
            if ([] === array_diff($missed, [$facet])) {
                $facets[$facet] = $this->counted($facets[$facet], $filters[$facet] ?? []);
            }
        }

        return $facets;
    }

    /**
     * The counts of a facet with the values of one more document, each once.
     *
     * @param array<string, int> $counts
     *
     * @return array<string, int>
     */
    private function counted(array $counts, mixed $values): array
    {
        foreach (array_unique($this->values($values)) as $value) {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * A filter of a document as text values: a scalar, or a list of them.
     *
     * @return array<array-key, string>
     */
    private function values(mixed $values): array
    {
        return array_map(static fn (mixed $one): string => \is_scalar($one) ? (string) $one : '', \is_array($values) ? $values : [$values]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function filters(mixed $json): array
    {
        $filters = \is_string($json) ? json_decode($json, true) : null;

        return \is_array($filters) ? $filters : [];
    }
}
