<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\ContentBundle\Application\Merger\TemplateMerger;
use Phil\ContentBundle\Application\Merger\WorkflowMerger;
use Phil\ContentBundle\Application\Pipeline\ContentAggregator;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Content\PublishedOrder;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Domain\ValueObject\Link;
use Phil\ContentBundle\Domain\ValueObject\PublishedRow;
use Phil\ContentBundle\Domain\ValueObject\PublishedRowQuery;
use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineContentRootRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineContentTransaction;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineDimensionContentRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrinePublishedRowRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineRouteRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\InvalidContentMapping;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ArticleDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ArticleId;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\EntityManagerFactory;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Page;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\PageDimension;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Markup\MarkupFormat;
use Phil\Slug\Slug;
use Phil\Url\Url;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The storage of rows on one database: the schema the mapping produces, the unique constraint over the
 * dimension, and the lookups of the repository.
 *
 * Each subclass gives the connection of its platform. SQLite in memory runs in the unit suite, with nothing
 * outside the process, so mutation testing reaches the repository and the listener; MySQL and PostgreSQL, in
 * `Tests\Integration`, take the URL of a server from an environment variable and **are skipped without it**.
 * The test creates its tables and drops them whether it passes or fails.
 *
 * @phpstan-import-type Params from DriverManager
 */
abstract class ContentStorageTestCase extends TestCase
{
    private const string NAIVE_TABLE = 'phil_content_probe_naive';

    protected EntityManager $entityManager;

    protected Connection $connection;

    private DoctrineDimensionContentRepository $repository;

    /**
     * @var list<ClassMetadata<object>>
     */
    private array $classes;

    /**
     * The subclass skips the test when its server is not configured.
     *
     * @return Params
     */
    abstract protected function connectionParameters(): array;

    #[Override]
    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::create($this->connectionParameters());
        $this->connection = $this->entityManager->getConnection();
        $this->repository = new DoctrineDimensionContentRepository($this->entityManager);
        $this->classes = array_map(
            $this->entityManager->getClassMetadata(...),
            [Article::class, ArticleDimension::class, Page::class, PageDimension::class, Route::class],
        );

        $this->dropTables();
        new SchemaTool($this->entityManager)->createSchema($this->classes);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->clear();
            $this->dropTables();
            $this->connection->close();
        }
    }

    public function testTheTableHoldsBothLocaleColumnsAndOneUniqueIndexOverTheDimension(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $columns = [];

        foreach ($schemaManager->introspectTableColumnsByUnquotedName('phil_content_probe_article_dimension') as $column) {
            $columns[strtolower($column->getName())] = $column->getNotnull();
        }

        $unique = array_values(array_filter(
            $schemaManager->introspectTableIndexesByUnquotedName('phil_content_probe_article_dimension'),
            static fn (Index $index): bool => $index->isUnique() && !$index->isPrimary(),
        ));

        self::assertFalse($columns['locale'], 'The locale of the unlocalized row is NULL.');
        self::assertTrue($columns['locale_key'], 'The sentinel column is never NULL.');
        self::assertTrue($columns['root_id']);
        self::assertCount(1, $unique);
        self::assertSame(['root_id', 'stage', 'version', 'locale_key'], array_map(
            static fn (IndexedColumn $column): string => strtolower($column->getColumnName()->getIdentifier()->getValue()),
            $unique[0]->getIndexedColumns(),
        ));
    }

    /**
     * What a migration diff reads: the tables read back declare what the mapping declares, types included,
     * so the comparator finds nothing to change.
     */
    public function testTheMappedTablesHaveNothingLeftToMigrate(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $comparator = $schemaManager->createComparator();
        $mapped = new SchemaTool($this->entityManager)->getSchemaFromMetadata($this->classes);

        foreach ($mapped->getTables() as $table) {
            $name = $table->getObjectName()->getUnqualifiedName()->getValue();
            $diff = $comparator->compareTables($schemaManager->introspectTableByUnquotedName($name), $table);

            self::assertTrue($diff->isEmpty(), \sprintf('The table "%s" differs from its mapping.', $name));
        }
    }

    public function testRowsAreStoredAndFoundBackByTheirDimension(): void
    {
        $article = $this->article();
        $published = new DateTimeImmutable('2026-05-04 12:00:00');
        $this->row($article, null, Stage::Draft, 1, 'article', ['image' => 'cover.png'], 'draft');
        $this->row($article, 'fr', Stage::Draft, 1, null, ['title' => 'Bonjour'], 'published', $published);
        $this->row($article, 'fr_CA', Stage::Draft, 1, null, ['title' => 'Allô']);
        $this->flushAndClear();

        $article = $this->reload($article);
        $shared = $this->repository->find($article, $this->key(null, Stage::Draft, 1));
        $french = $this->repository->find($article, $this->key('fr', Stage::Draft, 1));

        self::assertInstanceOf(ArticleDimension::class, $shared);
        self::assertInstanceOf(ArticleDimension::class, $french);
        self::assertSame('*/draft/1', $shared->dimension()->toString());
        self::assertSame('article', $shared->getTemplateKey());
        self::assertSame(['image' => 'cover.png'], $shared->getTemplateData());
        self::assertSame('draft', $shared->getWorkflowPlace());
        self::assertSame('fr/draft/1', $french->dimension()->toString());
        self::assertSame(['title' => 'Bonjour'], $french->getTemplateData());
        self::assertSame($published->format('c'), $french->getWorkflowPublished()?->format('c'));
        self::assertSame($article, $french->root());
    }

    /**
     * The unlocalized row stores `NULL` in `locale`, which is what a reader of the table expects, and the
     * sentinel in `locale_key`.
     */
    public function testTheUnlocalizedRowStoresANullLocaleAndTheSentinel(): void
    {
        $this->row($this->article(), null, Stage::Draft, 1);
        $this->flushAndClear();

        self::assertSame(
            [['locale' => null, 'locale_key' => '*']],
            $this->connection->fetchAllAssociative('SELECT locale, locale_key FROM phil_content_probe_article_dimension'),
        );
    }

    public function testAnUnlocalizedKeyFindsOnlyTheUnlocalizedRow(): void
    {
        $article = $this->article();
        $this->row($article, 'fr', Stage::Draft, 1);
        $this->flushAndClear();

        self::assertNull($this->repository->find($this->reload($article), $this->key(null, Stage::Draft, 1)));
    }

    public function testARowOfAnotherStageVersionOrRootIsNeverFound(): void
    {
        $article = $this->article();
        $other = $this->article();
        $this->row($article, 'fr', Stage::Live, 1);
        $this->row($article, 'fr', Stage::Draft, 2);
        $this->row($other, 'fr', Stage::Draft, 1);
        $this->flushAndClear();

        self::assertNull($this->repository->find($this->reload($article), $this->key('fr', Stage::Draft, 1)));
    }

    public function testTheRowsOfAStageAndVersionComeSharedFirstThenByLocale(): void
    {
        $article = $this->article();
        $other = $this->article();
        $this->row($article, 'fr_CA', Stage::Draft, 1);
        $this->row($article, 'en', Stage::Draft, 1);
        $this->row($article, null, Stage::Draft, 1);
        $this->row($article, 'fr', Stage::Draft, 1);
        $this->row($article, 'fr', Stage::Live, 1);
        $this->row($article, 'fr', Stage::Draft, 2);
        $this->row($other, 'de', Stage::Draft, 1);
        $this->flushAndClear();

        $rows = $this->repository->findAll($this->reload($article), Stage::Draft, Version::first());

        self::assertSame(
            ['*/draft/1', 'en/draft/1', 'fr/draft/1', 'fr_CA/draft/1'],
            array_map(static fn (DimensionContent $row): string => $row->dimension()->toString(), $rows),
        );
    }

    public function testARootWithoutRowsListsNone(): void
    {
        $article = $this->article();
        $this->flushAndClear();

        self::assertSame([], $this->repository->findAll($this->reload($article), Stage::Draft, Version::first()));
    }

    /**
     * The highest version as a number: 10 comes after 9, which a comparison of strings would deny.
     */
    public function testTheLatestVersionIsTheHighestOfTheStage(): void
    {
        $article = $this->article();
        $other = $this->article();
        $this->row($article, null, Stage::Draft, 9);
        $this->row($article, 'fr', Stage::Draft, 10);
        $this->row($article, 'fr', Stage::Live, 3);
        $this->row($other, 'fr', Stage::Draft, 12);
        $this->flushAndClear();

        $article = $this->reload($article);

        self::assertSame(10, $this->repository->latestVersion($article, Stage::Draft)?->number);
        self::assertSame(3, $this->repository->latestVersion($article, Stage::Live)?->number);
    }

    public function testARootWithoutRowsInAStageHasNoLatestVersion(): void
    {
        $article = $this->article();
        $this->row($article, 'fr', Stage::Draft, 1);
        $this->flushAndClear();

        self::assertNull($this->repository->latestVersion($this->reload($article), Stage::Live));
    }

    /**
     * The case the sentinel exists for.
     */
    public function testTwoUnlocalizedRowsOfOneDimensionAreRefused(): void
    {
        $article = $this->article();
        $this->row($article, null, Stage::Draft, 1);
        $this->row($article, null, Stage::Draft, 1);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }

    public function testTwoLocalizedRowsOfOneDimensionAreRefused(): void
    {
        $article = $this->article();
        $this->row($article, 'fr', Stage::Draft, 1);
        $this->row($article, 'fr', Stage::Draft, 1);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }

    /**
     * Why the sentinel: a unique index over the nullable `locale` itself admits two unlocalized rows of one
     * dimension, on this platform as on the others.
     */
    public function testAUniqueIndexOverTheNullableLocaleWouldAdmitTwoUnlocalizedRows(): void
    {
        $this->connection->executeStatement(\sprintf(
            'CREATE TABLE %s (root_id INT NOT NULL, locale VARCHAR(12) NULL, stage VARCHAR(8) NOT NULL, version INT NOT NULL)',
            self::NAIVE_TABLE,
        ));
        $this->connection->executeStatement(\sprintf(
            'CREATE UNIQUE INDEX %1$s_unique ON %1$s (root_id, stage, version, locale)',
            self::NAIVE_TABLE,
        ));
        $row = ['root_id' => 1, 'locale' => null, 'stage' => 'draft', 'version' => 1];

        $this->connection->insert(self::NAIVE_TABLE, $row);
        $this->connection->insert(self::NAIVE_TABLE, $row);

        self::assertSame(2, $this->rowCount(self::NAIVE_TABLE));
    }

    public function testDeletingARootDeletesItsRows(): void
    {
        $article = $this->article();
        $this->row($article, null, Stage::Draft, 1);
        $this->row($article, 'fr', Stage::Draft, 1);
        $this->flushAndClear();

        $this->entityManager->remove($this->reload($article));
        $this->entityManager->flush();

        self::assertSame(0, $this->rowCount('phil_content_probe_article_dimension'));
    }

    /**
     * The pipeline end to end: the aggregator over the Doctrine adapter, the localized row merged over the
     * shared one into a fresh row that is never persisted.
     */
    public function testTheAggregatorMergesStoredRows(): void
    {
        $article = $this->article();
        $this->row($article, null, Stage::Live, 1, 'article', ['image' => 'cover.png', 'title' => 'shared'], 'published');
        $this->row($article, 'fr_CA', Stage::Live, 1, null, ['title' => 'Allô']);
        $this->flushAndClear();

        $aggregator = new ContentAggregator($this->repository, [new TemplateMerger(), new WorkflowMerger()]);
        $chain = FallbackChain::of([Locale::fromString('fr'), Locale::fromString('fr_CA')]);
        $merged = $aggregator->aggregateAlong($this->reload($article), $chain, Stage::Live, Version::first());

        self::assertInstanceOf(ArticleDimension::class, $merged);
        self::assertSame('fr_CA/live/1', $merged->dimension()->toString());
        self::assertSame('article', $merged->getTemplateKey());
        self::assertSame(['image' => 'cover.png', 'title' => 'Allô'], $merged->getTemplateData());
        self::assertSame('published', $merged->getWorkflowPlace());
        self::assertFalse($this->entityManager->contains($merged));
    }

    /**
     * Every capability of the bundle on one row, written, flushed, and read back from a fresh entity manager.
     */
    public function testEveryCapabilityIsStoredAndReadBack(): void
    {
        $article = $this->article();
        $row = $this->row($article, 'fr', Stage::Draft, 1);
        $row->setSeoTitle('Été à Montréal');
        $row->setSeoDescription("Une description\nsur deux lignes");
        $row->setSeoKeywords(['larp', '2026']);
        $row->setSeoCanonicalUrl(Url::fromString('https://example.org/fr/ete'));
        $row->setSeoNoIndex(false);
        $row->setSeoHideInSitemap(true);
        $row->setExcerptTitle('Été');
        $row->setExcerptDescription('Un extrait');
        $row->setExcerptImage('/images/ete.png');
        $row->setLink(Link::toContent('page', '0192f3c4-5d6e-7f80-9a1b-2c3d4e5f6a7b', Locale::fromString('fr_CA')));
        $row->setSlug(Slug::fromString('ete-a-montreal'));
        $row->setBody(Body::of("# Été\n\n    code", MarkupFormat::Markdown, '<h1>Été</h1>'));

        $other = $this->row($article, 'en', Stage::Draft, 1);
        $other->setLink(Link::toUrl(Url::fromString('https://example.org/en')));
        $this->flushAndClear();

        $article = $this->reload($article);
        $french = $this->repository->find($article, $this->key('fr', Stage::Draft, 1));
        $english = $this->repository->find($article, $this->key('en', Stage::Draft, 1));

        self::assertInstanceOf(ArticleDimension::class, $french);
        self::assertSame('Été à Montréal', $french->getSeoTitle());
        self::assertSame("Une description\nsur deux lignes", $french->getSeoDescription());
        self::assertSame(['larp', '2026'], $french->getSeoKeywords());
        self::assertSame('https://example.org/fr/ete', $french->getSeoCanonicalUrl()?->toString());
        self::assertFalse($french->getSeoNoIndex());
        self::assertNull($french->getSeoNoFollow(), 'A directive the row does not set stays NULL.');
        self::assertTrue($french->getSeoHideInSitemap());
        self::assertSame('Été', $french->getExcerptTitle());
        self::assertSame('Un extrait', $french->getExcerptDescription());
        self::assertSame('/images/ete.png', $french->getExcerptImage());
        self::assertSame('page', $french->getLink()?->resourceKey);
        self::assertSame('0192f3c4-5d6e-7f80-9a1b-2c3d4e5f6a7b', $french->getLink()->resourceId);
        self::assertSame('fr_CA', $french->getLink()->locale?->value);
        self::assertSame('ete-a-montreal', $french->getSlug()?->value);
        self::assertSame("# Été\n\n    code", $french->getBody()?->source);
        self::assertSame('markdown', $french->getBody()->format);
        self::assertSame('<h1>Été</h1>', $french->getBody()->html);
        self::assertInstanceOf(ArticleDimension::class, $english);
        self::assertSame('https://example.org/en', $english->getLink()?->url?->toString());
        self::assertNull($english->getBody());
        self::assertNull($english->getSlug());
    }

    /**
     * Each row class carries the columns of its own capabilities, and no other.
     */
    public function testARowClassHasTheColumnsOfItsCapabilitiesOnly(): void
    {
        $columns = array_map(
            static fn (Column $column): string => strtolower($column->getName()),
            $this->connection->createSchemaManager()->introspectTableColumnsByUnquotedName('phil_content_probe_page_dimension'),
        );
        sort($columns);

        self::assertSame(['id', 'locale', 'locale_key', 'root_id', 'stage', 'template_data', 'template_key', 'version'], $columns);
    }

    public function testTheLatestVersionOfALocaleIgnoresTheOtherLocales(): void
    {
        $article = $this->article();
        $other = $this->article();
        $this->row($article, null, Stage::Live, 4);
        $this->row($article, 'fr', Stage::Live, 1);
        $this->row($article, 'fr', Stage::Live, 3);
        $this->row($article, 'en', Stage::Live, 2);
        $this->row($article, 'fr', Stage::Draft, 5);
        $this->row($other, 'fr', Stage::Live, 9);
        $this->flushAndClear();

        $article = $this->reload($article);

        self::assertSame(3, $this->repository->latestLocalizedVersion($article, Stage::Live, Locale::fromString('fr'))?->number);
        self::assertSame(2, $this->repository->latestLocalizedVersion($article, Stage::Live, Locale::fromString('en'))?->number);
        self::assertNull($this->repository->latestLocalizedVersion($article, Stage::Live, Locale::fromString('de')));
    }

    public function testTheVersionsOfALocaleComeLatestFirst(): void
    {
        $article = $this->article();
        $other = $this->article();
        $this->row($article, 'fr', Stage::Draft, 1);
        $this->row($article, 'fr', Stage::Draft, 3);
        $this->row($article, 'fr', Stage::Draft, 2);
        $this->row($article, 'en', Stage::Draft, 4);
        $this->row($article, null, Stage::Draft, 4);
        $this->row($article, 'fr', Stage::Live, 2);
        $this->row($other, 'fr', Stage::Draft, 5);
        $this->flushAndClear();

        $versions = $this->repository->findVersions($this->reload($article), Stage::Draft, Locale::fromString('fr'));

        self::assertSame(['fr/draft/3', 'fr/draft/2', 'fr/draft/1'], array_map(static fn (DimensionContent $row): string => $row->dimension()->toString(), $versions));
        self::assertSame([], $this->repository->findVersions($this->reload($article), Stage::Draft, Locale::fromString('de')));
    }

    public function testRowsAreAddedAndRemovedThroughTheRepository(): void
    {
        $article = $this->article();
        $row = $article->createDimensionContent($this->key('fr', Stage::Draft, 1));
        $this->repository->add($row);
        $this->flushAndClear();

        $article = $this->reload($article);
        $stored = $this->repository->find($article, $this->key('fr', Stage::Draft, 1));
        self::assertNotNull($stored);

        $this->repository->remove($stored);
        $this->entityManager->flush();

        self::assertSame(0, $this->rowCount('phil_content_probe_article_dimension'));
    }

    /**
     * A row removed and a row of the same dimension added, flushed in that order inside one transaction: what
     * publishing does to a live row.
     */
    public function testTheTransactionWritesARemovalBeforeTheReplacement(): void
    {
        $article = $this->article();
        $this->row($article, 'fr', Stage::Live, 1, null, ['title' => 'old']);
        $this->flushAndClear();
        $article = $this->reload($article);
        $transaction = new DoctrineContentTransaction($this->entityManager);

        $transaction->run(function () use ($article, $transaction): void {
            $old = $this->repository->find($article, $this->key('fr', Stage::Live, 1)) ?? self::fail('No row.');
            $this->repository->remove($old);
            $transaction->flush();
            $this->row($article, 'fr', Stage::Live, 1, null, ['title' => 'new']);
        });
        $this->entityManager->clear();

        $row = $this->repository->find($this->reload($article), $this->key('fr', Stage::Live, 1));
        self::assertInstanceOf(ArticleDimension::class, $row);
        self::assertSame(['title' => 'new'], $row->getTemplateData());
    }

    public function testAFailedTransactionKeepsNothing(): void
    {
        $article = $this->article();
        $this->flushAndClear();
        $article = $this->reload($article);
        $transaction = new DoctrineContentTransaction($this->entityManager);

        $failure = null;

        try {
            $transaction->run(function () use ($article, $transaction): void {
                $this->row($article, 'fr', Stage::Draft, 1);
                $transaction->flush();

                throw new RuntimeException('refused');
            });
        } catch (RuntimeException $runtimeException) {
            $failure = $runtimeException;
        }

        self::assertSame('refused', $failure->getMessage(), 'The failure is rethrown.');

        self::assertSame(0, $this->rowCount('phil_content_probe_article_dimension'));
        self::assertTrue($this->entityManager->isOpen(), 'The entity manager stays usable.');
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
        self::assertSame(0, $this->entityManager->getUnitOfWork()->size(), 'The entity manager forgets what the failed operation managed.');
    }

    public function testATransactionReturnsWhatItsOperationReturns(): void
    {
        self::assertSame(42, new DoctrineContentTransaction($this->entityManager)->run(static fn (): int => 42));
        self::assertSame(0, $this->connection->getTransactionNestingLevel(), 'The transaction is committed.');
    }

    public function testRoutesAreFoundBySlugAndByContent(): void
    {
        $routes = new DoctrineRouteRepository($this->entityManager);
        $news = ContentReference::of('article', '0192f3c4-5d6e-7f80-9a1b-2c3d4e5f6a7b');
        // Another kind, under the same identifier, written first.
        $routes->add(Route::current(ContentReference::of('page', $news->resourceId), Locale::fromString('fr'), Slug::fromString('meme-identifiant')));
        $old = Route::current($news, Locale::fromString('fr'), Slug::fromString('ouverture'));
        $old->retire();

        $routes->add($old);
        $routes->add(Route::current($news, Locale::fromString('fr'), Slug::fromString('grande-ouverture')));
        $routes->add(Route::current($news, Locale::fromString('en'), Slug::fromString('opening')));
        $routes->add(Route::current(ContentReference::of('page', '1'), Locale::fromString('fr'), Slug::fromString('ouverture')));
        $this->flushAndClear();

        $found = $routes->findBySlug('article', Locale::fromString('fr'), Slug::fromString('ouverture'));
        self::assertInstanceOf(Route::class, $found);
        self::assertTrue($found->isHistory());
        self::assertTrue($found->content()->equals($news));
        self::assertNull($routes->findBySlug('article', Locale::fromString('en'), Slug::fromString('ouverture')));
        self::assertSame('grande-ouverture', $routes->findCurrent($news, Locale::fromString('fr'))?->slug()->value);
        self::assertNull($routes->findCurrent($news, Locale::fromString('de')));
        self::assertSame(
            ['en/opening', 'fr/grande-ouverture', 'fr/ouverture'],
            array_map(static fn (Route $route): string => $route->locale()->value.'/'.$route->slug()->value, $routes->findAllOf($news)),
        );

        $routes->remove($found);
        $this->entityManager->flush();
        self::assertNull($routes->findBySlug('article', Locale::fromString('fr'), Slug::fromString('ouverture')));
    }

    public function testOneKindCannotHoldOneSlugTwiceInALocale(): void
    {
        $routes = new DoctrineRouteRepository($this->entityManager);
        $routes->add(Route::current(ContentReference::of('article', '1'), Locale::fromString('fr'), Slug::fromString('ouverture')));
        $routes->add(Route::current(ContentReference::of('article', '2'), Locale::fromString('fr'), Slug::fromString('ouverture')));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }

    public function testARootIsFoundBackByItsReference(): void
    {
        $roots = new DoctrineContentRootRepository($this->entityManager, ContentRoots::fromClassNames([Article::class, Page::class]));
        $article = $this->article();
        $this->flushAndClear();

        $reference = $roots->referenceOf($this->reload($article));
        self::assertSame('article:'.$article->id()->toString(), $reference->toString());

        $this->entityManager->clear();
        $found = $roots->find($reference);
        self::assertInstanceOf(Article::class, $found);
        self::assertTrue($found->id()->equals($article->id()));
        self::assertNull($roots->find(ContentReference::of('article', 'not-a-uuid')), 'An identifier its type refuses finds nothing.');
        self::assertNull($roots->find(ContentReference::of('article', ArticleId::generate()->toString())));
        self::assertCount(1, $roots->findAll('article'));
        self::assertSame([], $roots->findAll('page'));

        $page = new Page();
        $roots->add($page);
        $this->entityManager->flush();
        self::assertCount(1, $roots->findAll('page'));
    }

    /**
     * The live rows a locale serves, each in its highest version, with the shared row of that version; drafts,
     * other locales and older versions left out.
     */
    public function testThePublishedRowsOfALocaleComeWithTheirSharedRow(): void
    {
        $first = $this->article();
        $second = $this->article();
        $third = $this->article();
        $this->row($first, null, Stage::Live, 1, null, ['shared' => 'one']);
        $this->row($first, 'fr', Stage::Live, 1, null, ['title' => 'old']);
        $this->row($first, null, Stage::Live, 2, null, ['shared' => 'two']);
        $this->row($first, 'fr', Stage::Live, 2, null, ['title' => 'new']);
        $this->row($first, 'fr', Stage::Draft, 3);
        $this->row($second, 'fr', Stage::Live, 1, null, ['title' => 'alone']);
        $this->row($third, 'en', Stage::Live, 1);
        $this->flushAndClear();

        $rows = $this->published()->findPublished(new PublishedRowQuery('article', Locale::fromString('fr')));

        $described = array_map(static fn (PublishedRow $row): array => [
            $row->localized->dimension()->toString(),
            $row->localized instanceof ArticleDimension ? $row->localized->getTemplateData() : null,
            $row->shared instanceof ArticleDimension ? $row->shared->getTemplateData() : null,
        ], $rows);
        usort($described, static fn (array $a, array $b): int => $a[1] <=> $b[1]);

        self::assertSame([['fr/live/1', ['title' => 'alone'], null], ['fr/live/2', ['title' => 'new'], ['shared' => 'two']]], $described);
        self::assertSame([], $this->published()->findPublished(new PublishedRowQuery('article', Locale::fromString('de'))));
        self::assertSame([], $this->published()->findPublished(new PublishedRowQuery('page', Locale::fromString('fr'))));
    }

    /**
     * Each localized row takes the shared row of its own version, even when the root has a shared row in the version
     * another root's row was found in.
     */
    public function testEachPublishedRowTakesTheSharedRowOfItsOwnVersion(): void
    {
        $first = $this->article();
        $second = $this->article();
        $this->row($first, null, Stage::Live, 1, null, ['shared' => 'one']);
        $this->row($first, 'fr', Stage::Live, 1, null, ['title' => 'first']);
        $this->row($first, null, Stage::Live, 2, null, ['shared' => 'two']);
        $this->row($first, 'en', Stage::Live, 2, null, ['title' => 'english']);
        $this->row($second, null, Stage::Live, 2, null, ['shared' => 'second']);
        $this->row($second, 'fr', Stage::Live, 2, null, ['title' => 'second']);
        $this->flushAndClear();

        $described = array_map(static fn (PublishedRow $row): array => [
            $row->localized instanceof ArticleDimension ? $row->localized->getTemplateData() : null,
            $row->shared instanceof ArticleDimension ? $row->shared->getTemplateData() : null,
        ], $this->published()->findPublished(new PublishedRowQuery('article', Locale::fromString('fr'))));
        usort($described, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        self::assertSame([[['title' => 'first'], ['shared' => 'one']], [['title' => 'second'], ['shared' => 'second']]], $described);
    }

    public function testThePublishedRowsAreOrderedFilteredAndCut(): void
    {
        $roots = [];

        foreach (['b' => '2026-11-02', 'a' => null, 'c' => '2026-11-03', 'd' => '2026-11-01'] as $slug => $day) {
            $root = $this->article();
            $row = $this->row($root, 'fr', Stage::Live, 1, null, [], null, null === $day ? null : new DateTimeImmutable($day.' 12:00'));
            $row->setSlug('d' === $slug ? null : Slug::fromString($slug));
            $roots[$slug] = $root->id()->toString();
        }

        $this->flushAndClear();
        $french = new PublishedRowQuery('article', Locale::fromString('fr'), PublishedOrder::LatestPublished);

        self::assertSame(['c', 'b', 'd', 'a'], $this->slugs($french, $roots));
        self::assertSame(['b', 'd'], $this->slugs($french->page(1, 2), $roots));
        self::assertSame(['a', 'b', 'c'], $this->slugs(new PublishedRowQuery('article', Locale::fromString('fr'), PublishedOrder::Slug, withSlug: true), $roots));
        self::assertSame(['a', 'c'], $this->slugs(new PublishedRowQuery('article', Locale::fromString('fr'), PublishedOrder::Slug, resourceIds: [$roots['c'], $roots['a'], 'not-an-identifier']), $roots));
        self::assertSame([], $this->published()->findPublished(new PublishedRowQuery('article', Locale::fromString('fr'), resourceIds: ['not-an-identifier'])));
        self::assertSame([], $this->published()->findPublished(new PublishedRowQuery('article', Locale::fromString('fr'), resourceIds: [])));

        $byRoot = $roots;
        asort($byRoot);
        self::assertSame(array_keys($byRoot), $this->slugs(new PublishedRowQuery('article', Locale::fromString('fr')), $roots), 'By root, the order of the identifiers.');
    }

    /**
     * Identifiers no root can have, or none at all, are answered without a query: the table is not even read.
     */
    public function testIdentifiersNoRootCanHaveQueryNothing(): void
    {
        $this->connection->executeStatement('DROP TABLE phil_content_probe_article_dimension');

        self::assertSame([], $this->published()->findPublished(new PublishedRowQuery('article', Locale::fromString('fr'), resourceIds: ['not-an-identifier'])));
        self::assertSame([], $this->published()->findPublished(new PublishedRowQuery('article', Locale::fromString('fr'), resourceIds: [])));
    }

    public function testThePublishedRowsAreSelectedByAFieldOfTheirRoot(): void
    {
        $roots = [];

        foreach (['a' => 'podcast', 'b' => 'video', 'c' => 'podcast'] as $slug => $genre) {
            $root = $this->article();
            $root->setGenre($genre);
            $this->row($root, 'fr', Stage::Live, 1)->setSlug(Slug::fromString($slug));
            $roots[$slug] = $root->id()->toString();
        }

        $this->flushAndClear();

        self::assertSame(['a', 'c'], $this->slugs(new PublishedRowQuery('article', Locale::fromString('fr'), PublishedOrder::Slug, rootCriteria: ['genre' => 'podcast']), $roots));
    }

    public function testAPublishedRowQueryRefusesAFieldTheRootDoesNotMap(): void
    {
        $this->expectException(InvalidContentMapping::class);
        $this->expectExceptionMessageIsOrContains('maps no field "mood"');

        $this->published()->findPublished(new PublishedRowQuery('article', Locale::fromString('fr'), rootCriteria: ['mood' => 'calm']));
    }

    public function testAPublishedRowQueryRefusesTheIdentifierOfTheRootAsACriterion(): void
    {
        $this->expectException(InvalidContentMapping::class);
        $this->expectExceptionMessageIsOrContains('maps no field "id"');

        $this->published()->findPublished(new PublishedRowQuery('article', Locale::fromString('fr'), rootCriteria: ['id' => 'abc']));
    }

    public function testARowClassWithoutSlugsHasNoPublishedRowWithASlug(): void
    {
        self::assertSame([], $this->published()->findPublished(new PublishedRowQuery('page', Locale::fromString('fr'), withSlug: true)));
    }

    public function testTheCurrentRoutesOfManyContentsAreFoundAtOnce(): void
    {
        $routes = new DoctrineRouteRepository($this->entityManager);
        $french = Locale::fromString('fr');
        $routes->add(Route::current(ContentReference::of('article', '1'), $french, Slug::fromString('un')));
        $routes->add(Route::current(ContentReference::of('article', '2'), $french, Slug::fromString('deux')));
        $routes->add(Route::current(ContentReference::of('article', '2'), Locale::fromString('en'), Slug::fromString('two')));
        $routes->add(Route::current(ContentReference::of('page', '1'), $french, Slug::fromString('page')));

        $old = Route::current(ContentReference::of('article', '3'), $french, Slug::fromString('trois'));
        $old->retire();

        $routes->add($old);
        $this->flushAndClear();

        $slugs = array_map(static fn (Route $route): string => $route->slug()->value, $routes->findCurrentAmong('article', $french, ['1', '2', '3', '1']));
        sort($slugs);

        self::assertSame(['deux', 'un'], $slugs);
        $this->connection->executeStatement('DROP TABLE phil_content_route');
        self::assertSame([], $routes->findCurrentAmong('article', $french, []), 'No identifier, no query.');
    }

    /**
     * @param array<string, string> $roots the identifier of each slug's root
     *
     * @return list<string> the slug of each root found, in the order found
     */
    private function slugs(PublishedRowQuery $query, array $roots): array
    {
        return array_map(
            static fn (PublishedRow $row): string => (string) array_search($row->localized->root() instanceof Article ? $row->localized->root()->id()->toString() : '', $roots, true),
            $this->published()->findPublished($query),
        );
    }

    private function published(): DoctrinePublishedRowRepository
    {
        return new DoctrinePublishedRowRepository($this->entityManager, ContentRoots::fromClassNames([Article::class, Page::class]));
    }

    private function article(): Article
    {
        $article = new Article();
        $this->entityManager->persist($article);

        return $article;
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function row(
        Article $article,
        ?string $locale,
        Stage $stage,
        int $version,
        ?string $templateKey = null,
        array $templateData = [],
        ?string $place = null,
        ?DateTimeImmutable $published = null,
    ): ArticleDimension {
        $row = $article->createDimensionContent($this->key($locale, $stage, $version));
        self::assertInstanceOf(ArticleDimension::class, $row);
        $row->setTemplateKey($templateKey);
        $row->setTemplateData($templateData);
        $row->setWorkflowPlace($place);
        $row->setWorkflowPublished($published);

        $this->entityManager->persist($row);

        return $row;
    }

    private function key(?string $locale, Stage $stage, int $version): DimensionKey
    {
        return null === $locale
            ? DimensionKey::unlocalized($stage, Version::of($version))
            : DimensionKey::localized(Locale::fromString($locale), $stage, Version::of($version));
    }

    private function flushAndClear(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function reload(Article $article): Article
    {
        return $this->entityManager->find(Article::class, $article->id()) ?? self::fail('The article was not stored.');
    }

    /**
     * A driver may hand the count back as an integer or as a numeric string.
     */
    private function rowCount(string $table): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function dropTables(): void
    {
        new SchemaTool($this->entityManager)->dropSchema($this->classes);
        $this->connection->executeStatement('DROP TABLE IF EXISTS '.self::NAIVE_TABLE);
    }
}
