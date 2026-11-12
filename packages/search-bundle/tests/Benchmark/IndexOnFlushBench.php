<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Benchmark;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Generator;
use Phil\SearchBundle\Application\Search;
use Phil\SearchBundle\Application\SearchIndex;
use Phil\SearchBundle\Infrastructure\Doctrine\DbalSearchIndex;
use Phil\SearchBundle\Infrastructure\Doctrine\IndexOnFlushListener;
use Phil\SearchBundle\Infrastructure\Doctrine\SearchTable;
use Phil\SearchBundle\Infrastructure\Meilisearch\MeilisearchIndex;
use Phil\SearchBundle\Tests\Functional\App\Entity\Page;
use Phil\SearchBundle\Tests\Functional\App\Pages;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Every Doctrine flush that inserts or updates an indexed entity goes through the listener, which indexes the entities
 * of the flush together once it succeeds: one transaction for the database-table index, one documents request per
 * index for Meilisearch (answered here by a mock client, so the numbers hold no network time).
 *
 * The entities are scheduled in a real unit of work that is never committed, so each rev indexes the same flush
 * again, as an update would.
 *
 * @BeforeMethods("setUp")
 */
final class IndexOnFlushBench
{
    private readonly EntityManager $entityManager;

    private IndexOnFlushListener $listener;

    public function __construct()
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__).'/Functional/App/Entity']));
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        $this->listener = new IndexOnFlushListener(new Search($this->searchIndex('dbal'), [new Pages($this->entityManager)]));
    }

    /**
     * @param array{entities: int, adapter: string} $params
     */
    public function setUp(array $params): void
    {
        $this->listener = new IndexOnFlushListener(new Search($this->searchIndex($params['adapter']), [new Pages($this->entityManager)]));

        for ($i = 0; $i < $params['entities']; ++$i) {
            $this->entityManager->persist(new Page(\sprintf('page-%04d', $i), 'Soirée de jeux de rôle numéro '.$i, 0 === $i % 4 ? 'en' : 'fr'));
        }

        $this->entityManager->getUnitOfWork()->computeChangeSets();
    }

    /**
     * @ParamProviders({"adapters", "flushes"})
     *
     * @Revs(20)
     */
    public function benchFlush(): void
    {
        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));
        $this->listener->postFlush(new PostFlushEventArgs($this->entityManager));
    }

    /**
     * @return Generator<string, array{adapter: string}>
     */
    public function adapters(): Generator
    {
        yield 'database table' => ['adapter' => 'dbal'];
        yield 'Meilisearch' => ['adapter' => 'meilisearch'];
    }

    /**
     * @return Generator<string, array{entities: int}>
     */
    public function flushes(): Generator
    {
        yield '1 entity' => ['entities' => 1];
        yield '20 entities' => ['entities' => 20];
        yield '200 entities' => ['entities' => 200];
    }

    private function searchIndex(string $adapter): SearchIndex
    {
        if ('meilisearch' === $adapter) {
            return new MeilisearchIndex(new MockHttpClient(static fn (): MockResponse => new MockResponse('{"taskUid":1}', ['http_code' => 202])), 'http://meilisearch:7700');
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(SearchTable::configure(new Schema())->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        return new DbalSearchIndex($connection);
    }
}
