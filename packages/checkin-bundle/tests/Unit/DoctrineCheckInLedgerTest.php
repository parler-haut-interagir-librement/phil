<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Unit;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\Checkin\Attendance;
use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;
use Phil\CheckinBundle\Infrastructure\Doctrine\CheckInSchemaListener;
use Phil\CheckinBundle\Infrastructure\Doctrine\DoctrineCheckInLedger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The ledger on SQLite in memory, its table created from its own schema.
 */
#[CoversClass(DoctrineCheckInLedger::class)]
#[CoversClass(CheckInSchemaListener::class)]
final class DoctrineCheckInLedgerTest extends TestCase
{
    private Connection $connection;

    private DoctrineCheckInLedger $ledger;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->ledger = new DoctrineCheckInLedger($this->connection);

        foreach ($this->ledger->configureSchema(Schema::editor()->create())->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function testTheTableIsKeyedByScanAndBySequenceOfACredential(): void
    {
        $table = $this->connection->createSchemaManager()->introspectSchema()->getTable('phil_checkin_scan');

        self::assertSame('phil_checkin_scan', DoctrineCheckInLedger::TABLE);
        self::assertSame(['id'], array_map(static fn (object $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(IndexType::UNIQUE, $table->getIndex('phil_checkin_scan_sequence')->getType());
        self::assertSame(['credential_id', 'sequence'], $this->columns($table->getIndex('phil_checkin_scan_sequence')));
        self::assertSame(IndexType::REGULAR, $table->getIndex('phil_checkin_scan_checkpoint')->getType());
        self::assertSame(['checkpoint', 'scanned_at'], $this->columns($table->getIndex('phil_checkin_scan_checkpoint')));
        self::assertFalse($table->getColumn('sequence')->getNotnull());
        self::assertFalse($table->getColumn('conflict')->getNotnull());
        self::assertTrue($table->getColumn('outcome')->getNotnull());
        self::assertSame(128, $table->getColumn('credential_id')->getLength());
        self::assertSame(64, $table->getColumn('id')->getLength());
        self::assertSame(3, $table->getColumn('direction')->getLength());
        self::assertSame(32, $table->getColumn('conflict')->getLength());
        self::assertSame(32, $table->getColumn('outcome')->getLength());
        self::assertSame(64, $table->getColumn('checkpoint')->getLength());
        self::assertSame(64, $table->getColumn('device')->getLength());
    }

    public function testATableAlreadyThereIsLeftAlone(): void
    {
        $schema = $this->ledger->configureSchema(Schema::editor()->create());

        self::assertSame($schema, $this->ledger->configureSchema($schema));
    }

    public function testACredentialNeverScannedHasNoAttendance(): void
    {
        self::assertEquals(Attendance::none(), $this->ledger->attendance('T-1'));
        self::assertEquals(['T-1' => Attendance::none(), 'T-2' => Attendance::none()], $this->ledger->attendances(['T-1', 'T-2']));
    }

    public function testTheCheckInsAreKeptAndFoundAsTheyWere(): void
    {
        $online = $this->checkIn('s1', 'T-1', Direction::In, ScanOutcome::Admitted, Attendance::none(), 1000);
        $offline = CheckIn::decided(ScanId::of('s2'), Credential::of('T-1'), CheckpointId::of('stage'), Direction::In, ScanOutcome::Admitted, DeviceId::of('door-2'), new DateTimeImmutable('@1100'), new DateTimeImmutable('@1200'), Attendance::restore(1, 1, true, new DateTimeImmutable('@1000')), true, ScanOutcome::AlreadyAdmitted);

        self::assertTrue($this->ledger->append($online));
        self::assertTrue($this->ledger->append($offline));
        self::assertEquals($online, $this->ledger->find(ScanId::of('s1')));
        self::assertEquals($offline, $this->ledger->find(ScanId::of('s2')));
        self::assertNull($this->ledger->find(ScanId::of('s3')));
        self::assertSame(['0', '1'], array_map(static fn (mixed $offline): string => \is_scalar($offline) ? (string) $offline : '', $this->connection->fetchFirstColumn('SELECT offline FROM phil_checkin_scan ORDER BY id')));
    }

    public function testTheAttendanceIsFoldedFromTheAdmissionsAndCheckOuts(): void
    {
        $this->ledger->append($this->checkIn('s1', 'T-1', Direction::In, ScanOutcome::Admitted, Attendance::none(), 1000));
        $this->ledger->append($this->checkIn('s2', 'T-1', Direction::In, ScanOutcome::AlreadyAdmitted, Attendance::none(), 1050));
        $this->ledger->append($this->checkIn('s3', 'T-1', Direction::Out, ScanOutcome::CheckedOut, $this->ledger->attendance('T-1'), 1100));
        $this->ledger->append($this->checkIn('s4', 'T-1', Direction::In, ScanOutcome::Admitted, $this->ledger->attendance('T-1'), 1200));
        $this->ledger->append($this->checkIn('s5', 'T-2', Direction::In, ScanOutcome::Admitted, Attendance::none(), 1300));
        $this->ledger->append($this->checkIn('s6', 'T-2', Direction::Out, ScanOutcome::CheckedOut, $this->ledger->attendance('T-2'), 1400));

        self::assertEquals(Attendance::restore(3, 2, true, new DateTimeImmutable('@1200')), $this->ledger->attendance('T-1'));
        self::assertEquals(
            ['T-2' => Attendance::restore(2, 1, false, new DateTimeImmutable('@1300')), 'T-3' => Attendance::none(), 'T-1' => Attendance::restore(3, 2, true, new DateTimeImmutable('@1200'))],
            $this->ledger->attendances(['T-2', 'T-3', 'T-1']),
        );
        self::assertSame(['T-2', 'T-3', 'T-1'], array_keys($this->ledger->attendances(['T-2', 'T-3', 'T-1'])));
    }

    public function testAScanIdentifierIsRecordedOnceAndASequenceOncePerCredential(): void
    {
        $this->ledger->append($this->checkIn('s1', 'T-1', Direction::In, ScanOutcome::Admitted, Attendance::none(), 1000));

        self::assertFalse($this->ledger->append($this->checkIn('s1', 'T-2', Direction::In, ScanOutcome::Expired, Attendance::none(), 1000)));
        self::assertFalse($this->ledger->append($this->checkIn('s2', 'T-1', Direction::In, ScanOutcome::Admitted, Attendance::none(), 1000)));
        self::assertTrue($this->ledger->append($this->checkIn('s3', 'T-2', Direction::In, ScanOutcome::Admitted, Attendance::none(), 1000)));
        self::assertTrue($this->ledger->append($this->checkIn('s4', 'T-1', Direction::In, ScanOutcome::Expired, Attendance::none(), 1000)));
        self::assertTrue($this->ledger->append($this->checkIn('s5', 'T-1', Direction::In, ScanOutcome::Expired, Attendance::none(), 1000)));
        self::assertSame(1, $this->ledger->attendance('T-1')->version);
    }

    public function testTheTableJoinsTheSchemaOfTheLedgerConnectionOnly(): void
    {
        $listener = new CheckInSchemaListener($this->connection, $this->ledger);
        $schema = Schema::editor()->create();

        $other = new GenerateSchemaEventArgs($this->entityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])), $schema);
        $listener->postGenerateSchema($other);
        self::assertFalse($other->getSchema()->hasTable('phil_checkin_scan'));

        $event = new GenerateSchemaEventArgs($this->entityManager($this->connection), $schema);
        $listener->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable('phil_checkin_scan'));
    }

    private function checkIn(string $id, string $credential, Direction $direction, ScanOutcome $outcome, Attendance $seen, int $at): CheckIn
    {
        return CheckIn::decided(ScanId::of($id), Credential::of($credential, maxEntries: null), CheckpointId::of('hall'), $direction, $outcome, DeviceId::of('door-1'), new DateTimeImmutable('@'.$at), new DateTimeImmutable('@'.$at), $seen);
    }

    /**
     * @return list<string>
     */
    private function columns(Index $index): array
    {
        return array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $index->getIndexedColumns());
    }

    private function entityManager(Connection $connection): EntityManagerInterface
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }
}
