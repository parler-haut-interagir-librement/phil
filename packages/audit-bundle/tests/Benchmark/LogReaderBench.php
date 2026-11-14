<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Generator;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Read\AuditQuery;
use Phil\AuditBundle\Domain\Read\Cursor;
use Phil\AuditBundle\Infrastructure\Doctrine\AuditTable;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditLogReader;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditRowRepository;

/**
 * A page of the log is read on the table's indexes and found after a cursor: the page after 49 950 rows must cost what
 * the first one does, filtered or not, which an offset would not.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"audit"})
 */
final class LogReaderBench
{
    private const int ROWS = 50_000;

    private ?DbalAuditLogReader $reader = null;

    private AuditQuery $query;

    public function __construct()
    {
        $this->query = AuditQuery::all();
    }

    /**
     * The table is built once, in a file of the temporary directory that every iteration's process opens again: the
     * subject is the read, not the 50 000 inserts.
     *
     * @param array{depth: int, actor: bool} $params
     */
    public function setUp(array $params): void
    {
        $file = sys_get_temp_dir().'/phil-audit-log-reader-bench-'.self::ROWS.'.sqlite';

        if (!is_file($file)) {
            $this->build($file);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $file]);
        $reader = new DbalAuditLogReader($connection);
        $this->reader = $reader;
        $query = $params['actor'] ? AuditQuery::all()->byActor('acct-7') : AuditQuery::all();
        $cursor = null;

        if ($params['depth'] > 0) {
            // The row after which the page starts: the depth-th of the query, from the latest.
            $row = $reader->page($query->limitedTo(1)->after(null))->rows[0];
            $cursor = Cursor::after($row);

            for ($skipped = 1; $skipped < $params['depth']; $skipped += AuditQuery::MAX_LIMIT) {
                $page = $reader->page($query->limitedTo(min(AuditQuery::MAX_LIMIT, $params['depth'] - $skipped))->after($cursor));
                $cursor = Cursor::after($page->rows[\count($page->rows) - 1]);
            }
        }

        $this->query = $query->limitedTo(50)->after($cursor);
    }

    /**
     * @ParamProviders("pages")
     *
     * @Revs(500)
     *
     * @Iterations(5)
     */
    public function benchPage(): void
    {
        $this->reader?->page($this->query);
    }

    /**
     * @return Generator<string, array{depth: int, actor: bool}>
     */
    public function pages(): Generator
    {
        yield 'first page of 50 000 rows' => ['depth' => 0, 'actor' => false];
        yield 'page after 49 950 rows' => ['depth' => 49_950, 'actor' => false];
        yield 'first page of one actor' => ['depth' => 0, 'actor' => true];
        yield 'last page of one actor' => ['depth' => 950, 'actor' => true];
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
            $facts = Facts::of(0 === $i % 3 ? 'order.paid' : 'order.created', 'acct-'.($i % 50), Subject::of('order', (string) intdiv($i, 4)));
            $head = AuditRow::append($head, 'orders', RowKind::Event, $start->modify(\sprintf('+%d seconds', $i)), 'v1', \sprintf('{"order":%d}', $i), $facts);
            $repository->append($head);
        }

        $connection->commit();
        $connection->close();
        rename($building, $file);
    }
}
