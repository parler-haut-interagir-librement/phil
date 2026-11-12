<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\SearchBundle\Domain\Exception\InvalidSearch;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\FacetValue;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;
use Phil\SearchBundle\Infrastructure\Doctrine\DbalSearchIndex;
use Phil\SearchBundle\Infrastructure\Doctrine\SearchSchemaListener;
use Phil\SearchBundle\Infrastructure\Doctrine\SearchTable;
use Phil\SearchBundle\Tests\Unit\Fixtures\TextDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

#[CoversClass(DbalSearchIndex::class)]
#[CoversClass(SearchTable::class)]
#[CoversClass(SearchSchemaListener::class)]
final class DbalSearchIndexTest extends TestCase
{
    public function testDocumentsAreFoundByEveryWordAndFilter(): void
    {
        $index = new DbalSearchIndex($this->connection());
        $index->index(
            Document::of('articles', 'a', ['title' => 'Jeux de RÔLE', 'body' => 'Une soirée'], ['locale' => 'fr', 'tags' => ['jdr', 'soirée']]),
            Document::of('articles', 'b', ['title' => 'Role games'], ['locale' => 'en', 'tags' => 'jdr']),
            Document::of('articles', 'c', ['title' => '100% jeux_vidéo !'], ['locale' => 'fr', 'year' => 2026]),
            Document::of('pages', 'a', ['title' => 'Jeux'], ['locale' => 'fr']),
        );

        self::assertSame([['a', 'c'], 2], $this->found($index, 'articles', SearchQuery::of('JEUX')));
        self::assertSame([['a'], 1], $this->found($index, 'articles', SearchQuery::of('jeux rôle')));
        self::assertSame([['a', 'b'], 2], $this->found($index, 'articles', SearchQuery::of('', ['tags' => 'jdr'])));
        self::assertSame([['a', 'c'], 2], $this->found($index, 'articles', SearchQuery::of('', ['locale' => 'fr'])));
        self::assertSame([['c'], 1], $this->found($index, 'articles', SearchQuery::of('', ['year' => 2026])));
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('', ['locale' => 'fr', 'tags' => 'none'])));
        self::assertSame([['c'], 1], $this->found($index, 'articles', SearchQuery::of('100%')));
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('0!')));
        self::assertSame([['c'], 1], $this->found($index, 'articles', SearchQuery::of('jeux_vidéo')));
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('jeux_v_déo')));
        self::assertSame([['b'], 3], $this->found($index, 'articles', SearchQuery::of('', [], 1, 1)));
        self::assertSame([['a'], 1], $this->found($index, 'pages', SearchQuery::of('jeux')));
        $index->index(Document::of('articles', 'aa', ['title' => 'Plus de jeux, des jeux'], ['locale' => 'fr']), Document::of('articles', '0', ['title' => 'Jeux'], ['locale' => 'fr']));
        self::assertSame([['aa', '0', 'a', 'c'], 4], $this->found($index, 'articles', SearchQuery::of('jeux')));
        self::assertSame([['0', 'a'], 4], $this->found($index, 'articles', SearchQuery::of('jeux', [], 2, 1)));
        self::assertSame([['aa', 'a'], 2], $this->found($index, 'articles', SearchQuery::of('jeux de')));
        self::assertSame([['0', 'a', 'aa', 'b', 'c'], 5], $this->found($index, 'articles', SearchQuery::of('')));
        $index->remove('articles', ['0']);
        $index->remove('articles', ['aa']);

        $index->index(Document::of('articles', 'a', ['title' => 'Échecs']));
        self::assertSame([['c'], 1], $this->found($index, 'articles', SearchQuery::of('jeux')));
        self::assertSame([['a'], 1], $this->found($index, 'articles', SearchQuery::of('échecs')));

        $index->remove('articles', []);
        self::assertSame([['a', 'b', 'c'], 3], $this->found($index, 'articles', SearchQuery::of('')), 'nothing to remove');
        $index->remove('articles', ['a', 'b']);
        self::assertSame([['c'], 1], $this->found($index, 'articles', SearchQuery::of('')));
        self::assertSame([['a'], 1], $this->found($index, 'pages', SearchQuery::of('')));
        $index->clear('articles');
        self::assertSame([[], 0], $this->found($index, 'articles', SearchQuery::of('')));
        self::assertSame([['a'], 1], $this->found($index, 'pages', SearchQuery::of('')));
    }

    public function testFacetsAreCountedWithoutTheirOwnFilter(): void
    {
        $index = new DbalSearchIndex($this->connection());
        $index->index(
            Document::of('games', 'a', ['title' => 'Jeu de rôle'], ['genre' => ['jdr', 'fantasy', 'jdr'], 'year' => 2026, 'locale' => 'fr']),
            Document::of('games', 'b', ['title' => 'Jeu de plateau'], ['genre' => 'plateau', 'year' => 2025, 'locale' => 'fr']),
            Document::of('games', 'c', ['title' => 'Jeu de cartes'], ['genre' => ['cartes', 'fantasy'], 'year' => 2026, 'locale' => 'fr']),
            Document::of('games', 'd', ['title' => 'Role game'], ['genre' => 'jdr', 'year' => 2026, 'locale' => 'en']),
            Document::of('games', 'e', ['title' => 'Jeu sans genre'], ['locale' => 'fr']),
        );

        $all = $index->search('games', SearchQuery::of('', facets: ['genre', 'year']));
        self::assertSame([['a', 'b', 'c', 'd', 'e'], 5], [$all->ids, $all->total]);
        self::assertSame(['fantasy' => 2, 'jdr' => 2, 'cartes' => 1, 'plateau' => 1], $this->counts($all, 'genre'));
        self::assertSame([['2026', 3], ['2025', 1]], $this->pairs($all, 'year'));

        $chosen = $index->search('games', SearchQuery::of('jeu', ['genre' => ['jdr', 'cartes'], 'locale' => 'fr'], facets: ['genre', 'year', 'locale']));
        self::assertSame([['a', 'c'], 2], [$chosen->ids, $chosen->total]);
        self::assertSame(['fantasy' => 2, 'cartes' => 1, 'jdr' => 1, 'plateau' => 1], $this->counts($chosen, 'genre'), 'the genres the other filters leave');
        self::assertSame([['2026', 2]], $this->pairs($chosen, 'year'));
        self::assertSame(['fr' => 2], $this->counts($chosen, 'locale'), 'the locales the genres leave, the English one being no "jeu"');

        $none = $index->search('games', SearchQuery::of('', ['genre' => 'none', 'year' => 1999], facets: ['genre']));
        self::assertSame([[], 0, []], [$none->ids, $none->total, $this->counts($none, 'genre')]);
        self::assertSame([['a', 'c', 'd'], 3], $this->found($index, 'games', SearchQuery::of('', ['year' => [2026, 1999]])));
        self::assertSame([['a', 'c', 'd'], 3], $this->found($index, 'games', SearchQuery::of('', ['year' => '2026'])));
        self::assertSame([['b'], 5], $this->found($index, 'games', SearchQuery::of('', [], 1, 1, ['genre'])), 'a page of every document');
    }

    public function testATermIsFoundByAnyOfItsAlternatives(): void
    {
        $index = new DbalSearchIndex($this->connection());
        $index->index(
            Document::of('games', 'a', ['title' => 'Un JDR', 'body' => 'jdr jdr'], ['locale' => 'fr']),
            Document::of('games', 'b', ['title' => 'Un jeu', 'body' => "de\n  rôle épique"], ['locale' => 'fr']),
            Document::of('games', 'c', ['title' => 'Un jeu de plateau'], ['locale' => 'fr']),
            Document::of('games', 'd', ['title' => '100% jdr'], ['locale' => 'fr']),
        );

        $query = SearchQuery::of('jdr épique')->withTerms([['jdr', 'jeu de rôle'], ['épique']]);
        self::assertSame([['b'], 1], $this->found($index, 'games', $query));
        self::assertSame([['a', 'b', 'd'], 3], $this->found($index, 'games', SearchQuery::of('jdr')->withTerms([['jdr', 'jeu de rôle']])));
        self::assertSame([['b'], 1], $this->found($index, 'games', SearchQuery::of('x')->withTerms([['jeu de rôle']])), 'a phrase across a line break');
        self::assertSame([['d'], 1], $this->found($index, 'games', SearchQuery::of('x')->withTerms([['zzz', '100%']])));
        self::assertSame([[], 0], $this->found($index, 'games', SearchQuery::of('x')->withTerms([['0!', 'j_r']])));
    }

    /**
     * @return list<array{string, int}>
     */
    private function pairs(SearchResult $result, string $facet): array
    {
        return array_map(static fn (FacetValue $value): array => [$value->value, $value->count], $result->facet($facet));
    }

    /**
     * @return array<string, int>
     */
    private function counts(SearchResult $result, string $facet): array
    {
        $counts = [];

        foreach ($result->facet($facet) as $value) {
            $counts[$value->value] = $value->count;
        }

        return $counts;
    }

    public function testARebuildReplacesTheIndexInOneTransaction(): void
    {
        $connection = $this->connection();
        $index = new DbalSearchIndex($connection);
        $index->index(Document::of('articles', 'old', ['title' => 'Ancien']), Document::of('pages', 'p', ['title' => 'Page']));

        $seen = [];

        $index->rebuild('articles', (static function () use ($connection, &$seen): iterable {
            $seen[] = $connection->isTransactionActive();

            yield [Document::of('articles', 'a', ['title' => 'A']), Document::of('articles', 'b', ['title' => 'B'])];
            yield [Document::of('articles', 'c', ['title' => 'C'])];
        })());

        self::assertSame([true], $seen);
        self::assertSame([['a', 'b', 'c'], 3], $this->found($index, 'articles', SearchQuery::of('')));
        self::assertSame([['p'], 1], $this->found($index, 'pages', SearchQuery::of('')));

        try {
            $index->rebuild('articles', (static function (): iterable {
                yield [Document::of('articles', 'z', ['title' => 'Z'])];

                throw new RuntimeException('The provider failed.');
            })());
            self::fail('The rebuild went on.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The provider failed.', $runtimeException->getMessage());
        }

        self::assertSame([['a', 'b', 'c'], 3], $this->found($index, 'articles', SearchQuery::of('')));
        self::assertSame([['b'], 3], $this->found($index, 'articles', SearchQuery::of('', [], 1, 1)));
        self::assertSame([[], 3], $this->found($index, 'articles', SearchQuery::of('', [], 5, 3)));
    }

    public function testEveryDocumentIsReadAPageAtATimeAndItsCountMayComeAsText(): void
    {
        $driver = new TextDriver();
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], new Configuration()->setMiddlewares([$driver]));

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(SearchTable::configure(new Schema())->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        $index = new DbalSearchIndex($connection);
        $index->index(Document::of('articles', 'a', ['title' => 'A']), Document::of('articles', 'b', ['title' => 'B']), Document::of('articles', 'c', ['title' => 'C']));

        $driver->statements->exchangeArray([]);

        self::assertSame([['b'], 3], $this->found($index, 'articles', SearchQuery::of('', [], 1, 1)));
        self::assertCount(2, $driver->statements);
        self::assertStringContainsString('LIMIT', (string) $driver->statements[0]);
        self::assertStringContainsString('COUNT(*)', (string) $driver->statements[1]);
    }

    public function testAnIndexNameIsChecked(): void
    {
        $index = new DbalSearchIndex($this->connection());

        foreach ([
            static fn (): SearchResult => $index->search('../x', SearchQuery::of('')),
            static fn () => $index->remove('A', ['x']),
            static fn () => $index->clear('a b'),
            static fn () => $index->rebuild('a-b', []),
        ] as $call) {
            try {
                $call();
                self::fail('An index name was not checked.');
            } catch (InvalidSearch $invalidSearch) {
                self::assertStringStartsWith('An index is 1 to 64 lower-case letters', $invalidSearch->getMessage());
            }
        }
    }

    public function testOddRowsAreReadAsNothing(): void
    {
        $filters = new ReflectionMethod(DbalSearchIndex::class, 'filters');
        $missed = new ReflectionMethod(DbalSearchIndex::class, 'missed');
        $index = new DbalSearchIndex(self::connection(false));

        self::assertSame([[], [], ['a' => 1]], [$filters->invoke($index, null), $filters->invoke($index, '"x"'), $filters->invoke($index, '{"a":1}')]);
        self::assertSame(['a'], $missed->invoke($index, ['a' => [['nested']]], ['a' => 'nested']));
        self::assertSame([], $missed->invoke($index, ['a' => [['nested'], 'x']], ['a' => 'x']));
        self::assertSame(['a', 'b'], $missed->invoke($index, ['c' => 1], ['a' => '', 'b' => ['x', 'y'], 'c' => [2, 1]]));
    }

    public function testTheTableJoinsTheSchemaOfItsConnection(): void
    {
        $connection = $this->connection(false);
        $listener = new SearchSchemaListener($connection);
        $schema = new Schema();

        $elsewhere = new GenerateSchemaEventArgs($this->entityManager($this->connection(false)), $schema);
        $listener->postGenerateSchema($elsewhere);
        self::assertSame($schema, $elsewhere->getSchema(), 'The schema of another connection is left as it is.');
        self::assertFalse($schema->hasTable(SearchTable::NAME));

        $event = new GenerateSchemaEventArgs($this->entityManager($connection), $schema);

        $listener->postGenerateSchema($event);

        $schema = $event->getSchema();
        $table = $schema->getTable(SearchTable::NAME);
        self::assertSame([[64, 'string'], [255, 'string'], [null, 'text'], [null, 'json']], array_map(static fn (string $name): array => [$table->getColumn($name)->getLength(), Type::lookupName($table->getColumn($name)->getType())], ['index_name', 'document_id', 'content', 'filters']));
        self::assertSame(['index_name', 'document_id'], array_map(static fn (object $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame($schema, SearchTable::configure($schema));
        self::assertCount(1, $schema->getTables());
    }

    /**
     * @return array{list<string>, int}
     */
    private function found(DbalSearchIndex $index, string $name, SearchQuery $query): array
    {
        $result = $index->search($name, $query);

        return [$result->ids, $result->total];
    }

    private function connection(bool $withTable = true): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        if ($withTable) {
            foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(SearchTable::configure(new Schema())->getTables()) as $sql) {
                $connection->executeStatement($sql);
            }
        }

        return $connection;
    }

    private function entityManager(Connection $connection): EntityManagerInterface
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }
}
