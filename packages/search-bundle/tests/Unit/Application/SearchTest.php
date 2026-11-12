<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Application;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Phil\SearchBundle\Application\Search;
use Phil\SearchBundle\Application\SearchIndex;
use Phil\SearchBundle\Domain\Exception\InvalidSearch;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;
use Phil\SearchBundle\Infrastructure\Console\ReindexCommand;
use Phil\SearchBundle\Infrastructure\Doctrine\DbalSearchIndex;
use Phil\SearchBundle\Infrastructure\Doctrine\IndexOnFlushListener;
use Phil\SearchBundle\Infrastructure\Doctrine\SearchTable;
use Phil\SearchBundle\Tests\Unit\Fixtures\Article;
use Phil\SearchBundle\Tests\Unit\Fixtures\Articles;
use Phil\SearchBundle\Tests\Unit\Fixtures\RecordingSearchIndex;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(Search::class)]
#[CoversClass(IndexOnFlushListener::class)]
#[CoversClass(ReindexCommand::class)]
final class SearchTest extends TestCase
{
    /** @var array<int, UnitOfWork> by entity manager */
    private array $unitsOfWork = [];

    public function testResourcesAreIndexedRemovedAndFound(): void
    {
        $search = new Search($this->dbal(), [new Articles()]);
        $draft = new Article('b', 'Brouillon jeux', published: false);

        self::assertSame(1, $search->index(new Article('a', 'Jeux de rôle')));
        self::assertSame(0, $search->index($draft));
        self::assertSame(0, $search->index(new stdClass()));
        $both = new Search($this->dbal(), [new Articles(), new class extends Articles {
            public function index(): string
            {
                return 'news';
            }
        }]);
        self::assertSame([['articles', 'n'], ['news', 'n']], $both->addressesOf(new Article('n', 'News')));
        self::assertSame([], $both->addressesOf(new stdClass()));
        self::assertSame(2, new Search($this->dbal(), [new Articles(), new class extends Articles {
            public function index(): string
            {
                return 'news';
            }

            public function document(object $resource): Document
            {
                Assert::assertInstanceOf(Article::class, $resource);

                return Document::of('news', $resource->id, ['title' => $resource->title]);
            }
        }])->index(new Article('n', 'News')));
        self::assertSame(['a'], $search->search('articles', SearchQuery::of('jeux'))->ids);

        $search->index(new Article('a', 'Jeux de rôle', published: false));
        self::assertSame([], $search->search('articles', SearchQuery::of('jeux'))->ids);
        $search->index(new Article('a', 'Jeux de rôle'));
        $search->remove(new Article('a', ''));
        $search->remove(new stdClass());
        self::assertSame([], $search->search('articles', SearchQuery::of(''))->ids);
    }

    public function testAnIndexIsRebuiltInBatches(): void
    {
        $batches = [];
        $index = new class($batches) implements SearchIndex {
            /** @param list<int> $batches */
            public function __construct(
                public array &$batches,
            ) {
            }

            public function index(Document ...$documents): void
            {
                $this->batches[] = \count($documents);
            }

            public function remove(string $index, array $ids): void
            {
            }

            public function clear(string $index): void
            {
                $this->batches[] = -1;
            }

            public function rebuild(string $index, iterable $batches): void
            {
                $this->batches[] = -2;

                foreach ($batches as $batch) {
                    $this->index(...$batch);
                }
            }

            public function search(string $index, SearchQuery $query): SearchResult
            {
                return new SearchResult([], 0);
            }
        };
        $articles = new Articles(array_map(static fn (int $n): Article => new Article('a'.$n, 'Article '.$n, published: 3 !== $n), range(1, 5)));
        $search = new Search($index, [$articles, new Articles([new Article('z', 'Z')])], 2);

        self::assertSame(5, $search->reindex('articles'));
        self::assertSame([-2, 2, 2, 1], $batches);
        self::assertSame(['articles'], $search->indexes());
        self::assertSame(['articles', 'pages'], new Search($index, [$articles, $articles, new class extends Articles {
            public function index(): string
            {
                return 'pages';
            }
        }])->indexes());
        self::assertSame(500, new ReflectionProperty(Search::class, 'batch')->getValue(new Search($index, [])));

        $this->expectException(InvalidSearch::class);
        $this->expectExceptionMessageIsOrContains('No provider gives the index "pages".');

        $search->reindex('pages');
    }

    public function testARebuildThatFailsLeavesTheIndexAsItWas(): void
    {
        $index = $this->dbal();
        new Search($index, [new Articles([new Article('old', 'Ancien')])])->reindex('articles');
        $wrong = new class([new Article('a', 'A'), new Article('b', 'B')]) extends Articles {
            public function document(object $resource): Document
            {
                Assert::assertInstanceOf(Article::class, $resource);

                return Document::of('a' === $resource->id ? 'articles' : 'pages', $resource->id, ['title' => $resource->title]);
            }
        };

        try {
            new Search($index, [$wrong], 1)->reindex('articles');
            self::fail('A document of another index was rebuilt.');
        } catch (InvalidSearch $invalidSearch) {
            self::assertSame('A provider of the index "articles" gave a document of the index "pages".', $invalidSearch->getMessage());
        }

        self::assertSame(['old'], $index->search('articles', SearchQuery::of(''))->ids);
        self::assertSame(2, new Search($index, [new Articles([new Article('a', 'A'), new Article('b', 'B')])], 1)->reindex('articles'));
        self::assertSame(['a', 'b'], $index->search('articles', SearchQuery::of(''))->ids);
    }

    public function testIdentifiersToForgetGoAsAList(): void
    {
        $index = new class implements SearchIndex {
            /** @var list<array{string, array<array-key, string>}> */
            public array $removed = [];

            public function index(Document ...$documents): void
            {
            }

            public function remove(string $index, array $ids): void
            {
                $this->removed[] = [$index, $ids];
            }

            public function clear(string $index): void
            {
            }

            public function rebuild(string $index, iterable $batches): void
            {
            }

            public function search(string $index, SearchQuery $query): SearchResult
            {
                return new SearchResult([], 0);
            }
        };

        new Search($index, [])->forget('articles', ...['first' => 'a', 'second' => 'b']);

        self::assertSame([['articles', ['a', 'b']]], $index->removed);
    }

    public function testResourcesAreIndexedInOneCallAndForgottenInOnePerIndex(): void
    {
        $index = new RecordingSearchIndex();
        $news = new class extends Articles {
            public function index(): string
            {
                return 'news';
            }

            public function document(object $resource): ?Document
            {
                $document = parent::document($resource);

                return $document instanceof Document ? Document::of('news', $document->id, $document->text) : null;
            }
        };
        $search = new Search($index, [new Articles(), $news]);

        self::assertSame(4, $search->index(new Article('a', 'A'), new stdClass(), new Article('d', 'D', published: false), new Article('b', 'B'), new Article('e', 'E', published: false)));
        self::assertSame([
            ['index', ['articles:a', 'news:a', 'articles:b', 'news:b']],
            ['remove', ['d', 'e'], 'articles'],
            ['remove', ['d', 'e'], 'news'],
        ], $index->calls);

        $index->calls = [];
        self::assertSame(0, $search->index());
        self::assertSame(0, $search->index(new Article('x', 'X', published: false)));
        self::assertSame([['remove', ['x'], 'articles'], ['remove', ['x'], 'news']], $index->calls, 'no documents, no call to index them');

        $index->calls = [];
        $search->removeAt([['articles', 'a'], ['news', 'b'], ['articles', 'c']]);
        $search->removeAt([]);
        $search->remove(new Article('z', 'Z'));
        self::assertSame([['remove', ['a', 'c'], 'articles'], ['remove', ['b'], 'news'], ['remove', ['z'], 'articles'], ['remove', ['z'], 'news']], $index->calls);
    }

    public function testAFlushIndexesItsEntitiesTogetherInTheOrderOfItsChanges(): void
    {
        $index = new RecordingSearchIndex();
        $listener = new IndexOnFlushListener(new Search($index, [new Articles()]));
        $deleted = [new Article('x', 'X'), new Article('y', 'Y')];
        $entityManager = $this->entityManager([new Article('a', 'A'), new Article('b', 'B')], [new Article('c', 'C')], $deleted);

        $listener->onFlush(new OnFlushEventArgs($entityManager));
        $listener->onFlush(new OnFlushEventArgs($this->entityManager([new Article('d', 'D')], [], [new Article('z', 'Z')], $entityManager)));
        $listener->onFlush(new OnFlushEventArgs($this->entityManager([new Article('e', 'E')], [], [], $entityManager)));
        $listener->postFlush(new PostFlushEventArgs($entityManager));

        self::assertSame([
            ['index', ['articles:a', 'articles:b', 'articles:c']],
            ['remove', ['x', 'y'], 'articles'],
            ['index', ['articles:d']],
            ['remove', ['z'], 'articles'],
            ['index', ['articles:e']],
        ], $index->calls);

        $index->calls = [];
        $listener->onFlush(new OnFlushEventArgs($this->entityManager([], [], $deleted, $entityManager)));
        $listener->postFlush(new PostFlushEventArgs($entityManager));
        self::assertSame([['remove', ['x', 'y'], 'articles']], $index->calls, 'removals alone');
    }

    public function testIndexNamesAreChecked(): void
    {
        $search = new Search($this->dbal(), [new Articles()]);

        foreach ([
            static fn (): SearchResult => $search->search('../../public/x', SearchQuery::of('')),
            static fn (): int => $search->reindex('Articles'),
            static fn () => $search->forget('a/b', 'x'),
        ] as $call) {
            try {
                $call();
                self::fail('An index name was not checked.');
            } catch (InvalidSearch $invalidSearch) {
                self::assertStringStartsWith('An index is 1 to 64 lower-case letters', $invalidSearch->getMessage());
            }
        }
    }

    public function testAFlushIndexesWhatItChanges(): void
    {
        $search = new Search($this->dbal(), [new Articles()]);
        $listener = new IndexOnFlushListener($search);
        $article = new Article('a', 'Jeux');
        $entityManager = $this->entityManager([$article]);

        $listener->onFlush(new OnFlushEventArgs($entityManager));
        self::assertSame([], $search->search('articles', SearchQuery::of(''))->ids);
        $listener->postFlush(new PostFlushEventArgs($entityManager));
        self::assertSame(['a'], $search->search('articles', SearchQuery::of(''))->ids);

        $listener->onFlush(new OnFlushEventArgs($this->entityManager([new Article('b', 'B')], [], [], $entityManager)));
        $listener->onFlush(new OnFlushEventArgs($this->entityManager([], [], [$article], $entityManager)));
        $listener->postFlush(new PostFlushEventArgs($entityManager));
        self::assertSame(['b'], $search->search('articles', SearchQuery::of(''))->ids);
        $listener->postFlush(new PostFlushEventArgs($entityManager));
        self::assertSame(['b'], $search->search('articles', SearchQuery::of(''))->ids);
    }

    public function testADeletedEntityIsRemovedUnderTheIdentifierItHadBeforeTheFlush(): void
    {
        $search = new Search($this->dbal(), [new Articles()]);
        $search->index(new Article('7', 'Jeux'));

        $listener = new IndexOnFlushListener($search);
        $article = new Article('7', 'Jeux');
        $entityManager = $this->entityManager([], [], [$article]);

        $listener->onFlush(new OnFlushEventArgs($entityManager));
        // Doctrine empties a generated identifier once the row is deleted.
        $article->id = '';
        $listener->postFlush(new PostFlushEventArgs($entityManager));

        self::assertSame([], $search->search('articles', SearchQuery::of(''))->ids);
    }

    public function testWhatAFailedFlushNotedIsNeverApplied(): void
    {
        $search = new Search($this->dbal(), [new Articles()]);
        $listener = new IndexOnFlushListener($search);
        $failed = $this->entityManager([new Article('rolled-back', 'Annulé')]);
        $listener->onFlush(new OnFlushEventArgs($failed));

        // The worker goes on with a new entity manager; the one that failed is closed, its flush never ends.
        $next = $this->entityManager([new Article('kept', 'Gardé')]);
        $listener->onFlush(new OnFlushEventArgs($next));
        $listener->postFlush(new PostFlushEventArgs($next));
        self::assertSame(['kept'], $search->search('articles', SearchQuery::of(''))->ids);

        $cleared = $this->entityManager([new Article('cleared', 'Vidé')]);
        $listener->onFlush(new OnFlushEventArgs($cleared));
        $listener->onClear(new OnClearEventArgs($cleared));
        $listener->postFlush(new PostFlushEventArgs($cleared));

        $reset = $this->entityManager([new Article('reset', 'Remis à zéro')]);
        $listener->onFlush(new OnFlushEventArgs($reset));
        $listener->reset();
        $listener->postFlush(new PostFlushEventArgs($reset));

        self::assertSame(['kept'], $search->search('articles', SearchQuery::of(''))->ids);
    }

    public function testTheCommandRebuildsIndexes(): void
    {
        $search = new Search($this->dbal(), [new Articles([new Article('a', 'A'), new Article('b', 'B')])]);
        $tester = new CommandTester(new ReindexCommand($search));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("articles: 2 document(s).\n", $tester->getDisplay());
        $one = new CommandTester(new ReindexCommand($search));
        self::assertSame(Command::SUCCESS, $one->execute(['indexes' => ['articles', 'articles']]));
        self::assertSame("articles: 2 document(s).\narticles: 2 document(s).\n", $one->getDisplay());
        self::assertSame(['phil:search:reindex', 'Rebuilds search indexes from their providers'], [new ReindexCommand($search)->getName(), new ReindexCommand($search)->getDescription()]);
    }

    private function dbal(): DbalSearchIndex
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(SearchTable::configure(new Schema())->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        return new DbalSearchIndex($connection);
    }

    /**
     * An entity manager whose unit of work schedules these entities; given one, the same entity manager.
     *
     * @param list<object> $inserted
     * @param list<object> $updated
     * @param list<object> $deleted
     */
    private function entityManager(array $inserted, array $updated = [], array $deleted = [], ?EntityManagerInterface $same = null): EntityManagerInterface
    {
        $unitOfWork = self::createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn(array_combine(array_map(spl_object_id(...), $inserted), $inserted));
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn(array_combine(array_map(spl_object_id(...), $updated), $updated));
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn(array_combine(array_map(spl_object_id(...), $deleted), $deleted));

        if ($same instanceof EntityManagerInterface) {
            $this->unitsOfWork[spl_object_id($same)] = $unitOfWork;

            return $same;
        }

        $entityManager = self::createStub(EntityManagerInterface::class);
        $this->unitsOfWork[spl_object_id($entityManager)] = $unitOfWork;
        $entityManager->method('getUnitOfWork')->willReturnCallback(fn (): UnitOfWork => $this->unitsOfWork[spl_object_id($entityManager)]);

        return $entityManager;
    }

    public function testASearchOfABadIndexNameIsRefusedBeforeTheIndexIsAsked(): void
    {
        $index = new RecordingSearchIndex();

        try {
            new Search($index, [])->search('Bad Name', SearchQuery::of('x'));
            self::fail('The index was asked.');
        } catch (InvalidSearch $invalidSearch) {
            self::assertStringContainsString('"Bad Name"', $invalidSearch->getMessage());
        }

        self::assertSame([], $index->calls);
    }
}
