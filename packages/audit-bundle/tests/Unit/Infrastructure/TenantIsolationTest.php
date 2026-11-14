<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use Pdo\Sqlite;
use Phil\AuditBundle\Application\AuditLog;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Contract\AuditLogReader;
use Phil\AuditBundle\Domain\Read\AuditPage;
use Phil\AuditBundle\Domain\Read\AuditQuery;
use Phil\AuditBundle\Domain\Read\Order;
use Phil\AuditBundle\Infrastructure\Doctrine\AuditTable;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditLogReader;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditRowRepository;
use Phil\AuditBundle\Tests\Unit\Fixture\FixedTenant;
use Phil\AuditBundle\Tests\Unit\Fixture\JsonSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * One tenant never reads another tenant's trail, even on a table whose columns fold case and accents as a MySQL
 * server's default collation does: SQLite is given such a collation, `mysql_ai_ci`, on the tenant, the actor and the
 * record's identifier, and the rows of `ACME`, `acmé` and `Acme` that it answers for `acme` are left out.
 */
#[CoversClass(DbalAuditLogReader::class)]
#[CoversClass(AuditLog::class)]
final class TenantIsolationTest extends TestCase
{
    private Connection $connection;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $native = $this->connection->getNativeConnection();
        self::assertInstanceOf(Sqlite::class, $native);
        $native->createCollation('mysql_ai_ci', static fn (string $left, string $right): int => strcmp(self::folded($left), self::folded($right)));

        foreach (AuditTable::configure($this->connection->createSchemaManager()->introspectSchema(), $this->connection->getDatabasePlatform())->toSql($this->connection->getDatabasePlatform()) as $sql) {
            foreach (['actor VARCHAR(128)', 'subject_id VARCHAR(128)', 'tenant VARCHAR(255)'] as $column) {
                $sql = str_replace($column.' DEFAULT NULL', $column.' DEFAULT NULL COLLATE mysql_ai_ci', $sql);
            }

            $this->connection->executeStatement($sql);
        }

        $clock = new MockClock('2026-11-13 09:00 UTC');
        $trail = new AuditTrail(new DbalAuditRowRepository($this->connection), new JsonSerializer(), $clock);

        foreach ([['acme', 'acct-7', 'a1'], ['ACME', 'ACCT-7', 'A1'], ['acmé', 'acct-7', 'a1'], ['zeta', 'acct-9', 'z1'], ['Acme', 'Acct-7', 'A1'], ['acme', 'acct-7', 'a1']] as $index => [$tenant, $actor, $id]) {
            $trail->record('orders', ['i' => $index], Facts::of('order.created', $actor, Subject::of('order', $id), $tenant));
            $clock->modify('+1 second');
        }
    }

    public function testTheCollationFoldsAsAServersDefaultOneWould(): void
    {
        self::assertEquals(5, $this->connection->fetchOne('SELECT COUNT(*) FROM phil_audit_row WHERE tenant = ?', ['acme']), 'The table itself answers acme with ACME, acmé and Acme.');
    }

    public function testTheReaderKeepsOnlyTheRowsOfTheTenantAsked(): void
    {
        $reader = new DbalAuditLogReader($this->connection);

        self::assertSame([6, 1], $this->sequences($reader->page(AuditQuery::all()->inTenant('acme'))));
        self::assertSame([2], $this->sequences($reader->page(AuditQuery::all()->inTenant('ACME'))));
        self::assertSame([3], $this->sequences($reader->page(AuditQuery::all()->inTenant('acmé'))));
        self::assertSame([4], $this->sequences($reader->page(AuditQuery::all()->inTenant('zeta'))));
        self::assertSame([6, 3, 1], $this->sequences($reader->page(AuditQuery::all()->byActor('acct-7'))), 'The actor compares byte by byte too.');
        self::assertSame([6, 3, 1], $this->sequences($reader->page(AuditQuery::historyOf(Subject::of('order', 'a1'))->ordered(Order::NewestFirst))), 'And the record.');
        self::assertSame([5, 2], $this->sequences($reader->page(AuditQuery::all()->about(Subject::of('order', 'A1')))));
    }

    public function testPagesGoOnPastTheRowsLeftOut(): void
    {
        $reader = new DbalAuditLogReader($this->connection);
        $query = AuditQuery::all()->inTenant('acme')->ordered(Order::OldestFirst)->limitedTo(2);
        $seen = [];

        do {
            $page = $reader->page($query);
            $seen = [...$seen, ...$this->sequences($page)];
            self::assertLessThanOrEqual(2, \count($page->rows));
            $query = $query->after($page->next);
        } while (!$page->isLast());

        self::assertSame([1, 6], $seen, 'A page may hold fewer rows than its limit; the next one follows the last row read.');
    }

    public function testARowIsReadByItsExactPlace(): void
    {
        $reader = new DbalAuditLogReader($this->connection);

        self::assertSame('ACME', $reader->row('orders', 2)?->facts->tenant);
        self::assertNull($reader->row('orders', 9));
        self::assertNull($reader->row('ORDERS', 2), 'The segment as written, not one that folds to it.');
    }

    public function testTheLogHoldsATenantToItsOwnRowsWhateverTheReaderAnswers(): void
    {
        $log = new AuditLog(new DbalAuditLogReader($this->connection), new FixedTenant('acme'));

        self::assertSame([6, 1], $this->sequences($log->page(AuditQuery::all()->inTenant('zeta'))), 'The query names zeta; the scope holds it to acme.');
        self::assertSame([6, 1], $this->sequences($log->page(AuditQuery::all())));
        self::assertNull($log->row('orders', 2));
        self::assertSame('acme', $log->row('orders', 1)?->facts->tenant);

        // A reader of the application's own that answers whatever folds to the tenant.
        $loose = new readonly class($this->connection) implements AuditLogReader {
            public function __construct(
                private Connection $connection,
            ) {
            }

            #[Override]
            public function page(AuditQuery $query): AuditPage
            {
                $rows = $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE tenant = ? ORDER BY sequence', DbalAuditRowRepository::COLUMNS, AuditTable::NAME), [$query->tenant]);

                return new AuditPage(array_map(DbalAuditRowRepository::rowOf(...), $rows), null);
            }

            #[Override]
            public function row(string $segment, int $sequence): ?AuditRow
            {
                return null;
            }
        };

        self::assertSame([1, 6], $this->sequences(new AuditLog($loose, new FixedTenant('acme'))->page(AuditQuery::all())));
        self::assertSame([2], $this->sequences(new AuditLog($loose)->page(AuditQuery::all()->inTenant('ACME'))), 'Without a scope, the query is held to with === as well.');
    }

    /**
     * @return list<int>
     */
    private function sequences(AuditPage $page): array
    {
        return array_map(static fn (AuditRow $row): int => $row->sequence, $page->rows);
    }

    /**
     * A text without its case and its accents, as `utf8mb4_0900_ai_ci` compares the letters of these tests.
     */
    private static function folded(string $text): string
    {
        return strtolower(strtr($text, ['é' => 'e', 'É' => 'e']));
    }
}
