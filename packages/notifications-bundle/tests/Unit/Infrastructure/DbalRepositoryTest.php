<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalNotificationRepository;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationTables;
use Phil\NotificationsBundle\Infrastructure\Doctrine\StoredRow;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The repository on SQLite in memory, its tables created from their definition.
 */
#[CoversClass(DbalNotificationRepository::class)]
#[CoversClass(StoredRow::class)]
final class DbalRepositoryTest extends RepositoryTestCase
{
    private ?Connection $connection = null;

    public function testTheRowsAreWrittenInUtc(): void
    {
        $repository = $this->repository();
        $repository->record(new Notification('a', 'welcome', '42', null, new DateTimeImmutable('2026-11-08 07:00', new DateTimeZone('America/Toronto'))), false);
        $repository->markRead('42', 'a', new DateTimeImmutable('2026-11-08 08:00', new DateTimeZone('America/Toronto')));

        self::assertSame(
            ['created_at' => '2026-11-08 12:00:00', 'read_at' => '2026-11-08 13:00:00', 'data' => '{}', 'group_key' => 'welcome|', 'occurrences' => 1],
            $this->connection()->fetchAssociative('SELECT created_at, read_at, data, group_key, occurrences FROM phil_notifications'),
        );
        self::assertSame('2026-11-08 12:00:00 UTC', $repository->find('42', 'a')?->createdAt->format('Y-m-d H:i:s e'));
    }

    public function testTheInstantsAreUtcColumns(): void
    {
        $table = NotificationTables::configure(Schema::editor()->create())->getTable(NotificationTables::NOTIFICATIONS);

        foreach (['created_at', 'updated_at', 'read_at', 'digested_at'] as $column) {
            self::assertSame(Types::DATETIME_UTC_IMMUTABLE, $table->getColumn($column)->getTypeName(), $column);
        }
    }

    public function testARowThatIsNoNotificationIsRefused(): void
    {
        $row = ['id' => 'a', 'recipient' => '42', 'type' => 'welcome', 'data' => '{}', 'group_key' => 'welcome|', 'state' => 'unread', 'occurrences' => 1, 'created_at' => '2026-11-08 12:00:00', 'updated_at' => '2026-11-08 12:00:00'];

        foreach ([
            'The state of a stored notification is "unread", "read" or "archived".' => ['state' => 'gone'],
            'The column "data" of a stored notification is a JSON object.' => ['data' => '"x"'],
            'The column "data" of a stored notification is a JSON object' => ['data' => '{'],
            'The column "occurrences" of a stored notification is a whole number.' => ['occurrences' => 'many'],
            'The column "created_at" of a stored notification is a date and time.' => ['created_at' => 'yesterday'],
            'The column "read_at" of a stored notification is a date and time.' => ['read_at' => '2026-11-08'],
            'The data value of "x"' => ['data' => '{"x": 1.5}'],
        ] as $message => $override) {
            $connection = $this->connection(fresh: true);
            $connection->insert(NotificationTables::NOTIFICATIONS, [...$row, ...$override]);

            try {
                new DbalNotificationRepository($connection)->find('42', 'a');
                self::fail($message);
            } catch (InvalidNotification $invalidNotification) {
                self::assertStringStartsWith($message, $invalidNotification->getMessage());
            }
        }
    }

    public function testTheTypedReaderChecksEachColumn(): void
    {
        $row = new StoredRow(['text' => 'a', 'number' => 5, 'digits' => '-12', 'newline' => "12\n", 'float' => 1.5, 'big' => '1234567890123456789', 'json' => '[1, 2]', 'nested' => '[[1]]', 'deep' => '[[[1]]]', 'date' => '2026-11-08 12:00:00', 'null' => null], 'thing');

        self::assertSame('a', $row->string('text'));
        self::assertNull($row->nullableString('null'));
        self::assertNull($row->nullableString('missing'));
        self::assertSame('a', $row->nullableString('text'));
        self::assertSame(5, $row->int('number'));
        self::assertSame(-12, $row->int('digits'));
        self::assertSame([1, 2], $row->json('json'));
        self::assertSame([[1]], $row->json('nested'));
        $platform = new SQLitePlatform();
        self::assertSame('2026-11-08 12:00:00.000000 UTC', $row->date('date', $platform)->format('Y-m-d H:i:s.u e'));
        self::assertNull($row->nullableDate('null', $platform));
        self::assertSame('2026-11-08 12:00:00', $row->nullableDate('date', $platform)?->format('Y-m-d H:i:s'));

        foreach ([
            'The column "number" of a stored thing is a text.' => static fn (): string => $row->string('number'),
            'The column "missing" of a stored thing is a text.' => static fn (): string => $row->string('missing'),
            'The column "float" of a stored thing is a whole number.' => static fn (): int => $row->int('float'),
            'The column "big" of a stored thing is a whole number.' => static fn (): int => $row->int('big'),
            'The column "text" of a stored thing is a whole number.' => static fn (): int => $row->int('text'),
            'The column "newline" of a stored thing is a whole number.' => static fn (): int => $row->int('newline'),
            'The column "deep" of a stored thing is a JSON object.' => static fn (): array => $row->json('deep'),
            'The column "text" of a stored thing is a date and time.' => static fn (): DateTimeImmutable => $row->date('text', $platform),
        ] as $message => $read) {
            try {
                $read();
                self::fail($message);
            } catch (InvalidNotification $invalidNotification) {
                self::assertSame($message, $invalidNotification->getMessage());
            }
        }

        self::assertSame(123456789012345678, new StoredRow(['n' => '123456789012345678'], 'thing')->int('n'));
        self::assertSame(7, new StoredRow(['n' => '7'], 'thing')->int('n'));
    }

    #[Override]
    protected function repository(): NotificationRepository
    {
        return new DbalNotificationRepository($this->connection());
    }

    private function connection(bool $fresh = false): Connection
    {
        if ($fresh || !$this->connection instanceof Connection) {
            $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

            foreach ($this->connection->getDatabasePlatform()->getCreateTablesSQL(NotificationTables::configure(Schema::editor()->create())->getTables()) as $sql) {
                $this->connection->executeStatement($sql);
            }
        }

        return $this->connection;
    }
}
