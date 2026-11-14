<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Generator;
use Override;
use Phil\AuditBundle\Application\DashboardFeed;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Contract\FeedCheckpoints;
use Phil\AuditBundle\Infrastructure\Doctrine\AuditTable;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditRowRepository;
use Phil\AuditBundle\Infrastructure\Export\JsonLinesFactExporter;

/**
 * The feed reads from its place on the `(segment, sequence)` key and writes each batch as JSON lines: a batch of 500
 * rows from deep in a segment of 50 000 must cost what the first batch does.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"audit"})
 */
final class FeedBench
{
    private const int ROWS = 50_000;

    private ?DashboardFeed $feed = null;

    /**
     * The table is built once, in a file of the temporary directory: the subject is the batch, not the inserts. The
     * place never moves, so that each revolution sends the same batch.
     *
     * @param array{place: int} $params
     */
    public function setUp(array $params): void
    {
        $file = sys_get_temp_dir().'/phil-audit-feed-bench-'.self::ROWS.'.sqlite';

        if (!is_file($file)) {
            $this->build($file);
        }

        $place = $params['place'];
        $this->feed = new DashboardFeed(
            new DbalAuditRowRepository(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $file])),
            new JsonLinesFactExporter('php://memory'),
            new readonly class($place) implements FeedCheckpoints {
                public function __construct(
                    private int $place,
                ) {
                }

                #[Override]
                public function position(string $feed, string $segment): int
                {
                    return max(0, $this->place);
                }

                #[Override]
                public function advance(string $feed, string $segment, int $sequence): void
                {
                }
            },
        );
    }

    /**
     * @ParamProviders("places")
     *
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function benchBatch(): void
    {
        $this->feed?->feedSegment('orders', 500);
    }

    /**
     * @return Generator<string, array{place: int}>
     */
    public function places(): Generator
    {
        yield 'first 500 rows' => ['place' => 0];
        yield '500 rows after 49 000' => ['place' => 49_000];
    }

    private function build(string $file): void
    {
        $building = $file.'.'.bin2hex(random_bytes(4));
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $building]);

        foreach (AuditTable::configure($connection->createSchemaManager()->introspectSchema(), $connection->getDatabasePlatform())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $repository = new DbalAuditRowRepository($connection);
        $start = new DateTimeImmutable('2026-11-01 00:00 UTC');
        $head = null;
        $connection->beginTransaction();

        for ($i = 1; $i <= self::ROWS; ++$i) {
            $facts = Facts::of('order.created', 'acct-'.($i % 50), Subject::of('order', (string) $i), 'shop-'.($i % 3));
            $head = AuditRow::append($head, 'orders', RowKind::Event, $start->modify(\sprintf('+%d seconds', $i)), 'v1', \sprintf('{"order":%d}', $i), $facts);
            $repository->append($head);
        }

        $connection->commit();
        $connection->close();
        rename($building, $file);
    }
}
