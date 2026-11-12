<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\BrowserSecurityBundle\Domain\Enum\Disposition;
use Phil\BrowserSecurityBundle\Domain\ValueObject\StoredReport;
use Phil\BrowserSecurityBundle\Domain\ValueObject\ViolationReport;
use Phil\BrowserSecurityBundle\Infrastructure\Doctrine\DbalReportRepository;
use Phil\BrowserSecurityBundle\Infrastructure\Doctrine\ReportSchemaListener;
use Phil\BrowserSecurityBundle\Infrastructure\Doctrine\ReportTable;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\RacingConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalReportRepository::class)]
#[CoversClass(ReportTable::class)]
#[CoversClass(ReportSchemaListener::class)]
final class DbalReportRepositoryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (ReportTable::configure(Schema::editor()->create())->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function testAReportIsCountedOncePerFingerprint(): void
    {
        $repository = new DbalReportRepository($this->connection);
        $report = new ViolationReport('https://example.com/a', 'inline', 'script-src-elem', Disposition::Report, 'https://example.com/app.js', 3, 7, 200, 'alert(1)');
        $repository->record($report, '192.0.2.0', new DateTimeImmutable('2026-11-01 10:00:00'));
        $repository->record($report, null, new DateTimeImmutable('2026-11-02 10:00:00'));
        $repository->record(new ViolationReport('https://example.com/b', '', 'img-src'), null, new DateTimeImmutable('2026-11-01 12:00:00'));

        self::assertEquals([
            new StoredReport($report, 2, new DateTimeImmutable('2026-11-01 10:00:00'), new DateTimeImmutable('2026-11-02 10:00:00')),
            new StoredReport(new ViolationReport('https://example.com/b', '', 'img-src'), 1, new DateTimeImmutable('2026-11-01 12:00:00'), new DateTimeImmutable('2026-11-01 12:00:00')),
        ], $repository->latest(10));
        self::assertCount(1, $repository->latest(1));
        self::assertSame($report->fingerprint(), $this->connection->fetchOne('SELECT fingerprint FROM phil_csp_report WHERE hits = 2'));
    }

    public function testTheLatestByLastTimeThenByRow(): void
    {
        $repository = new DbalReportRepository($this->connection);
        $at = new DateTimeImmutable('2026-11-01 10:00:00');
        $repository->record(new ViolationReport('https://example.com/a', '', 'img-src'), 'x', $at);
        $repository->record(new ViolationReport('https://example.com/b', '', 'img-src'), 'y', $at);

        self::assertSame(['https://example.com/b', 'https://example.com/a'], array_map(static fn (StoredReport $stored): string => $stored->report->documentUri, $repository->latest(5)));
        self::assertSame('y', $repository->latest(1)[0]->client);
    }

    public function testANewFingerprintBeyondTheCapacityIsNotKept(): void
    {
        $repository = new DbalReportRepository($this->connection, 1);
        $at = new DateTimeImmutable('2026-11-01 10:00:00');
        $first = new ViolationReport('https://example.com/a', '', 'img-src');
        $repository->record($first, null, $at);
        $repository->record(new ViolationReport('https://example.com/b', '', 'img-src'), null, $at);
        $repository->record($first, null, $at);

        self::assertCount(1, $repository->latest(10));
        self::assertSame(2, $repository->latest(10)[0]->count);
        self::assertSame(10_000, DbalReportRepository::DEFAULT_CAPACITY);
    }

    public function testTwoFirstReportsEndInTheSameRow(): void
    {
        // Another worker inserts the same fingerprint between the update and the insert.
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => RacingConnection::class]);

        foreach (ReportTable::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $repository = new DbalReportRepository($connection);
        $repository->record(new ViolationReport('https://example.com/a', '', 'img-src'), 'c', new DateTimeImmutable('2026-11-01 10:00:00'));

        $latest = $repository->latest(10);
        self::assertCount(1, $latest);
        self::assertSame(2, $latest[0]->count);
        self::assertSame('c', $latest[0]->client);
    }

    public function testThePurge(): void
    {
        $repository = new DbalReportRepository($this->connection);
        $repository->record(new ViolationReport('https://example.com/a', '', 'img-src'), null, new DateTimeImmutable('2026-10-01'));
        $repository->record(new ViolationReport('https://example.com/b', '', 'img-src'), null, new DateTimeImmutable('2026-11-01'));
        $repository->record(new ViolationReport('https://example.com/c', '', 'img-src'), null, new DateTimeImmutable('2026-11-02'));

        self::assertSame(1, $repository->purge(new DateTimeImmutable('2026-11-01')));
        self::assertCount(2, $repository->latest(10));
    }

    public function testARowWithAnUnknownDisposition(): void
    {
        $repository = new DbalReportRepository($this->connection);
        $repository->record(new ViolationReport('https://example.com/a', '', 'img-src'), null, new DateTimeImmutable('2026-11-01'));

        $this->connection->executeStatement("UPDATE phil_csp_report SET disposition = 'other', line_number = '12'");

        self::assertSame(Disposition::Enforce, $repository->latest(1)[0]->report->disposition);
        self::assertSame(12, $repository->latest(1)[0]->report->line);
    }

    public function testTheTableIsAddedOnce(): void
    {
        $schema = ReportTable::configure(Schema::editor()->create());

        self::assertSame($schema, ReportTable::configure($schema));
    }

    public function testANumberReadAsText(): void
    {
        // A driver without native types gives every number as text.
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE phil_csp_report (id TEXT, fingerprint TEXT, document_uri TEXT, blocked_uri TEXT, effective_directive TEXT, disposition TEXT, source_file TEXT, line_number TEXT, column_number TEXT, status_code TEXT, sample TEXT, client TEXT, hits TEXT, first_seen_at TEXT, last_seen_at TEXT)');
        $connection->executeStatement("INSERT INTO phil_csp_report VALUES ('1', 'f', 'https://example.com/', '', 'img-src', 'report', NULL, '12', 'x', '200', NULL, NULL, 'many', '2026-11-01 10:00:00', '2026-11-02 10:00:00')");

        $stored = new DbalReportRepository($connection)->latest(1)[0];

        self::assertSame(12, $stored->report->line);
        self::assertNull($stored->report->column);
        self::assertSame(200, $stored->report->statusCode);
        self::assertSame(0, $stored->count);
        self::assertSame(Disposition::Report, $stored->report->disposition);
        self::assertSame('2026-11-02 10:00:00', $stored->lastSeenAt->format('Y-m-d H:i:s'));
    }

    public function testTheColumnsOfTheTable(): void
    {
        $table = ReportTable::configure(Schema::editor()->create())->getTable(ReportTable::NAME);
        $columns = [];

        foreach ($table->getColumns() as $column) {
            $columns[] = [$column->getTypeName(), $column->getLength(), $column->getFixed(), $column->getNotnull(), $column->getAutoincrement()];
        }

        self::assertSame([
            [Types::INTEGER, null, false, true, true],
            [Types::STRING, 64, true, true, false],
            [Types::STRING, 2048, false, true, false],
            [Types::STRING, 2048, false, true, false],
            [Types::STRING, 64, false, true, false],
            [Types::STRING, 8, false, true, false],
            [Types::STRING, 2048, false, false, false],
            [Types::INTEGER, null, false, false, false],
            [Types::INTEGER, null, false, false, false],
            [Types::INTEGER, null, false, false, false],
            [Types::STRING, 160, false, false, false],
            [Types::STRING, 64, false, false, false],
            [Types::BIGINT, null, false, true, false],
            [Types::DATETIME_IMMUTABLE, null, false, true, false],
            [Types::DATETIME_IMMUTABLE, null, false, true, false],
        ], $columns);

        foreach (['id', 'fingerprint', 'document_uri', 'blocked_uri', 'effective_directive', 'disposition', 'source_file', 'line_number', 'column_number', 'status_code', 'sample', 'client', 'hits', 'first_seen_at', 'last_seen_at'] as $name) {
            self::assertTrue($table->hasColumn($name), $name);
        }
    }

    public function testTheListenerAddsTheTableForItsConnectionOnly(): void
    {
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ([[$this->connection, true], [$other, false]] as [$connection, $added]) {
            $manager = self::createStub(EntityManagerInterface::class);
            $manager->method('getConnection')->willReturn($connection);
            $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());

            new ReportSchemaListener($this->connection)->postGenerateSchema($event);

            self::assertSame($added, $event->getSchema()->hasTable(ReportTable::NAME));
        }
    }
}
