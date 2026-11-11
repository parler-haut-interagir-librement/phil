<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Benchmark;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Generator;
use Override;
use Phil\ReferencesBundle\Application\References;
use Phil\ReferencesBundle\Application\ReferenceTarget;
use Phil\ReferencesBundle\Domain\ValueObject\Usage;
use Phil\ReferencesBundle\Infrastructure\Doctrine\DbalUsageRepository;
use Phil\ReferencesBundle\Infrastructure\Doctrine\UsageTable;
use Phil\Resource\ResourceReference;

/**
 * The broken-links check walks every distinct target, asks its kind whether it still exists, and reads the usages of
 * each one that is gone: its cost grows with the targets in use and the share of them that are broken.
 *
 * The target answers from a set in memory, so that the numbers hold the check and its queries, not the existence test.
 *
 * @BeforeMethods("setUp")
 */
final class BrokenReferencesBench
{
    private readonly Connection $connection;

    private readonly DbalUsageRepository $usages;

    private References $references;

    public function __construct()
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($this->connection->getDatabasePlatform()->getCreateTablesSQL(UsageTable::configure(new Schema())->getTables()) as $sql) {
            $this->connection->executeStatement($sql);
        }

        $this->usages = new DbalUsageRepository($this->connection);
        $this->references = new References($this->usages, [], []);
    }

    /**
     * @param array{targets: int, broken: int} $params
     */
    public function setUp(array $params): void
    {
        $connection = $this->connection;
        $usages = $this->usages;
        $existing = [];
        $connection->beginTransaction();

        // Ten links per page; each image is used by two pages.
        for ($page = 0, $pages = intdiv($params['targets'] * 2, 10); $page < $pages; ++$page) {
            $source = ResourceReference::of('page', 'page-'.$page);
            $links = [];

            for ($link = 0; $link < 10; ++$link) {
                $image = ($page * 10 + $link) % $params['targets'];
                $links[] = Usage::of($source, 'blocks.'.$link.'.image', ResourceReference::of('image', 'image-'.$image));
            }

            $usages->replace($source, $links);
        }

        $connection->commit();

        for ($image = 0; $image < $params['targets']; ++$image) {
            // One image in `broken` percent is gone.
            if ($image % 100 >= $params['broken']) {
                $existing['image-'.$image] = true;
            }
        }

        $this->references = new References($usages, [], [new readonly class($existing) implements ReferenceTarget {
            /**
             * @param array<string, true> $existing
             */
            public function __construct(
                private array $existing,
            ) {
            }

            #[Override]
            public function kind(): string
            {
                return 'image';
            }

            #[Override]
            public function exists(string $id): bool
            {
                return isset($this->existing[$id]);
            }

            #[Override]
            public function referenceOf(object $resource): ?ResourceReference
            {
                return null;
            }
        }]);
    }

    /**
     * @ParamProviders({"sizes", "shares"})
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchBroken(): void
    {
        $this->references->broken();
    }

    /**
     * @return Generator<string, array{targets: int}>
     */
    public function sizes(): Generator
    {
        yield '1k targets' => ['targets' => 1_000];
        yield '10k targets' => ['targets' => 10_000];
    }

    /**
     * @return Generator<string, array{broken: int}>
     */
    public function shares(): Generator
    {
        yield '1% broken' => ['broken' => 1];
        yield '10% broken' => ['broken' => 10];
        yield 'all broken' => ['broken' => 100];
    }
}
