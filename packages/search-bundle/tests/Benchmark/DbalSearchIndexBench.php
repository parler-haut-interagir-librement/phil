<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Benchmark;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Generator;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Infrastructure\Doctrine\DbalSearchIndex;
use Phil\SearchBundle\Infrastructure\Doctrine\SearchTable;

/**
 * Every search request of a site without an engine goes through the database-table index: a search with words or
 * filters reads every candidate row into PHP, where it is filtered, scored, sorted and paged, so its cost grows with
 * the size of the index and the share of documents that match.
 *
 * @BeforeMethods("setUp")
 */
final readonly class DbalSearchIndexBench
{
    /**
     * Words a document is written with; "jeux" is in one document in three, "échecs" in one in a hundred.
     */
    private const array WORDS = ['soirée', 'partie', 'table', 'joueurs', 'règles', 'carte', 'dés', 'campagne', 'monde', 'histoire', 'personnage', 'aventure', 'ville', 'groupe', 'samedi', 'club'];

    private Connection $connection;

    private DbalSearchIndex $index;

    public function __construct()
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($this->connection->getDatabasePlatform()->getCreateTablesSQL(SearchTable::configure(Schema::editor()->create())->getTables()) as $sql) {
            $this->connection->executeStatement($sql);
        }

        $this->index = new DbalSearchIndex($this->connection);
    }

    /**
     * @param array{documents: int, query: string, filters: array<string, string|list<string>>, facets: list<string>} $params
     */
    public function setUp(array $params): void
    {
        $this->fill($this->connection, $params['documents']);
    }

    /**
     * The first page of ten results, as a search page asks it.
     *
     * @ParamProviders({"sizes", "queries"})
     *
     * @Revs(3)
     *
     * @Iterations(3)
     *
     * @param array{documents: int, query: string, filters: array<string, string|list<string>>, facets: list<string>} $params
     */
    public function benchSearch(array $params): void
    {
        $this->index->search('articles', SearchQuery::of($params['query'], $params['filters'], facets: $params['facets']));
    }

    /**
     * @return Generator<string, array{documents: int}>
     */
    public function sizes(): Generator
    {
        yield '1k documents' => ['documents' => 1_000];
        yield '10k documents' => ['documents' => 10_000];
        yield '50k documents' => ['documents' => 50_000];
    }

    /**
     * @return Generator<string, array{query: string, filters: array<string, string|list<string>>, facets: list<string>}>
     */
    public function queries(): Generator
    {
        yield 'no words' => ['query' => '', 'filters' => [], 'facets' => []];
        yield 'filter only' => ['query' => '', 'filters' => ['locale' => 'fr'], 'facets' => []];
        yield 'common word' => ['query' => 'jeux', 'filters' => [], 'facets' => []];
        yield 'rare word' => ['query' => 'échecs', 'filters' => [], 'facets' => []];
        yield 'two words and a filter' => ['query' => 'jeux soirée', 'filters' => ['locale' => 'fr'], 'facets' => []];
        yield 'a word, two values and two facets' => ['query' => 'jeux', 'filters' => ['tags' => ['tag-1', 'tag-2']], 'facets' => ['tags', 'locale']];
    }

    /**
     * Writes the rows directly, in one transaction, so that the setup of 50k documents stays short.
     */
    private function fill(Connection $connection, int $documents): void
    {
        $connection->beginTransaction();
        $insert = $connection->prepare(\sprintf('INSERT INTO %s (index_name, document_id, content, filters) VALUES (?, ?, ?, ?)', SearchTable::NAME));
        $count = \count(self::WORDS);

        for ($i = 0; $i < $documents; ++$i) {
            $words = [];

            for ($w = 0; $w < 60; ++$w) {
                $words[] = self::WORDS[($i * 7 + $w * 13) % $count];
            }

            if (0 === $i % 3) {
                $words[] = 'jeux';
            }

            if (0 === $i % 100) {
                $words[] = 'échecs';
            }

            $insert->bindValue(1, 'articles');
            $insert->bindValue(2, \sprintf('article-%06d', $i));
            $insert->bindValue(3, 'Article '.$i.' '.implode(' ', $words));
            $insert->bindValue(4, (string) json_encode(['locale' => 0 === $i % 4 ? 'en' : 'fr', 'tags' => ['jdr', 'tag-'.($i % 50)]]));
            $insert->executeStatement();
        }

        $connection->commit();
    }
}
