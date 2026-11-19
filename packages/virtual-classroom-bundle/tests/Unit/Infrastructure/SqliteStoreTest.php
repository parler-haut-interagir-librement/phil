<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Override;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\ClassroomTables;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\DbalClassroomStore;
use Phil\VirtualClassroomBundle\Infrastructure\Doctrine\ClassroomSchemaListener;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\AbstractLogger;
use Stringable;

#[CoversClass(DbalClassroomStore::class)]
#[CoversClass(ClassroomTables::class)]
#[CoversClass(ClassroomSchemaListener::class)]
final class SqliteStoreTest extends StoreTestCase
{
    private ?Connection $connection = null;

    public function testTheTablesAreAddedOnceWithTheirPrefix(): void
    {
        $tables = ClassroomTables::for(new SQLitePlatform(), 'lms');
        $schema = $tables->configure(Schema::editor()->create());

        self::assertTrue($schema->hasTable('lms_room'));
        self::assertTrue($schema->hasTable('lms_stay'));
        self::assertTrue($schema->hasTable('lms_recording'));
        self::assertSame($schema, $tables->configure($schema));
        self::assertSame(['room_id', 'participant_id', 'joined_at'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $schema->getTable('lms_stay')->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['room_id', 'recording_id', 'format'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $schema->getTable('lms_recording')->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertTrue($schema->getTable('lms_room')->hasIndex('lms_room_owner'));
        self::assertTrue($schema->getTable('lms_room')->hasIndex('lms_room_status'));
        self::assertTrue($schema->getTable('lms_stay')->hasIndex('lms_stay_participant'));
        self::assertFalse($schema->getTable('lms_room')->getColumn('owner')->getNotnull());
        self::assertTrue($schema->getTable('lms_room')->getColumn('provider')->getNotnull());
        self::assertSame(64, $schema->getTable('lms_room')->getColumn('id')->getLength());
        self::assertSame(190, $schema->getTable('lms_stay')->getColumn('participant_id')->getLength());
        self::assertSame(255, $schema->getTable('lms_room')->getColumn('title')->getLength());
        self::assertSame(16, $schema->getTable('lms_room')->getColumn('status')->getLength());
        self::assertSame(32, $schema->getTable('lms_recording')->getColumn('format')->getLength());
        self::assertSame('lms_recording', $tables->recordingTable()->getObjectName()->toString());
    }

    public function testAMissingTableIsAddedBesideThoseThere(): void
    {
        $tables = ClassroomTables::for(new SQLitePlatform());
        $schema = Schema::editor()->setTables($tables->roomTable())->create();

        $configured = $tables->configure($schema);

        self::assertTrue($configured->hasTable('phil_virtual_classroom_stay'));
        self::assertTrue($configured->hasTable('phil_virtual_classroom_recording'));
    }

    public function testTheKeysCompareByteForByteOnMySqlAndPostgreSql(): void
    {
        foreach ([new MySQLPlatform(), new MariaDBPlatform()] as $platform) {
            $column = ClassroomTables::for($platform)->stayTable()->getColumn('participant_id');
            self::assertSame('utf8mb4_bin', $column->getCollation());
            self::assertSame('utf8mb4', $column->getCharset());
        }

        self::assertSame('C', ClassroomTables::for(new PostgreSQLPlatform())->roomTable()->getColumn('owner')->getCollation());
        self::assertNull(ClassroomTables::for(new SQLitePlatform())->roomTable()->getColumn('owner')->getCollation());
    }

    public function testTheSchemaListenerAddsTheTablesForItsConnectionOnly(): void
    {
        $connection = $this->connection();
        $listener = new ClassroomSchemaListener($connection, 'lms');
        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);
        $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());

        $listener->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable('lms_room'));

        $other = self::createStub(EntityManagerInterface::class);
        $other->method('getConnection')->willReturn($this->connection());
        $untouched = new GenerateSchemaEventArgs($other, Schema::editor()->create());
        $listener->postGenerateSchema($untouched);
        self::assertFalse($untouched->getSchema()->hasTable('lms_room'));
    }

    public function testARowThatCannotBeReadIsLeftOut(): void
    {
        $store = $this->store();
        $connection = $this->connection;
        self::assertInstanceOf(Connection::class, $connection);
        $row = ['provider' => 'main', 'title' => 'Algebra', 'record' => 0, 'max_minutes' => null, 'owner' => 'o', 'status' => 'open', 'session_key' => null, 'created_at' => 0, 'opened_at' => null, 'ended_at' => null];
        $connection->insert('phil_virtual_classroom_room', ['id' => 'bad-status', ...$row, 'status' => 'lost']);
        $connection->insert('phil_virtual_classroom_room', ['id' => 'Bad Id', ...$row]);
        $connection->insert('phil_virtual_classroom_room', ['id' => 'bad-provider', ...$row, 'provider' => 'Main']);
        $connection->insert('phil_virtual_classroom_room', ['id' => 'bad-time', ...$row, 'created_at' => 'soon']);
        $connection->insert('phil_virtual_classroom_stay', ['room_id' => 'room1', 'participant_id' => 'a@b', 'joined_at' => 0, 'left_at' => null]);
        $connection->insert('phil_virtual_classroom_stay', ['room_id' => 'room1', 'participant_id' => 'ada', 'joined_at' => 'soon', 'left_at' => null]);
        $connection->insert('phil_virtual_classroom_recording', ['room_id' => 'room1', 'recording_id' => 'a b', 'format' => 'video', 'url' => null, 'published' => 1]);

        $store->saveRecording(new Recording('good', RoomId::fromString('room1'), 'video'));

        self::assertNull($store->room(RoomId::fromString('bad-status')));
        self::assertNull($store->room(RoomId::fromString('bad-provider')));
        self::assertNull($store->room(RoomId::fromString('bad-time')));
        self::assertSame([], $store->openRooms(10));
        self::assertSame(['ada'], self::names($store->present(RoomId::fromString('room1'))));
        self::assertNull($store->roomOfOwner('o'));
        self::assertSame([], $store->stays(RoomId::fromString('room1')));
        self::assertSame([], $store->staysOf(ParticipantId::fromString('ada')));
        self::assertSame(['ada'], self::names($store->present(RoomId::fromString('room1'))), 'An identifier that is not one is not present.');
        $open = new Room(RoomId::fromString('good-room'), 'main', 'Good', false, null, null, self::at(0));
        $open->open(null, self::at(0));

        $store->saveRoom($open);
        self::assertSame(['good-room'], array_map(static fn (Room $room): string => $room->id->value, $store->openRooms(10)));
        self::assertList($store->openRooms(10));
        self::assertList($store->present(RoomId::fromString('room1')));
        self::assertSame(['good'], array_map(static fn (Recording $recording): string => $recording->id, $store->recordings(RoomId::fromString('room1'))));
    }

    public function testTheFlagsAreStoredAsBooleans(): void
    {
        $store = $this->store();
        $connection = $this->connection;
        self::assertInstanceOf(Connection::class, $connection);
        $store->saveRoom(new Room(RoomId::fromString('room1'), 'main', 'Algebra', true, null, null, self::at(0)));
        $store->saveRoom(new Room(RoomId::fromString('room2'), 'main', 'Algebra', false, null, null, self::at(0)));
        $store->saveRecording(new Recording('rec-1', RoomId::fromString('room1'), 'video', null, true));
        $store->saveRecording(new Recording('rec-2', RoomId::fromString('room1'), 'video', null, false));
        $store->saveRecording(new Recording('rec-1', RoomId::fromString('room1'), 'video', null, false));

        self::assertSame([[1], [0]], $connection->fetchAllNumeric('SELECT record FROM phil_virtual_classroom_room ORDER BY id'));
        self::assertSame([[0], [0]], $connection->fetchAllNumeric('SELECT published FROM phil_virtual_classroom_recording ORDER BY recording_id'));
    }

    public function testARoomAlreadySavedIsUpdatedWithoutAnotherInsert(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $statements = [];

            #[Override]
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $sql = $context['sql'] ?? null;

                if (\is_string($sql)) {
                    $this->statements[] = strtoupper(explode(' ', $sql, 2)[0]);
                }
            }
        };
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], new Configuration()->setMiddlewares([new Middleware($logger)]));
        $store = new DbalClassroomStore($connection);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        $room = new Room(RoomId::fromString('room1'), 'main', 'Algebra', false, null, null, self::at(0));
        $store->saveRoom($room);
        $room->open(null, self::at(10));
        $logger->statements = [];

        $store->saveRoom($room);

        self::assertSame(['UPDATE'], $logger->statements);
    }

    public function testAMomentStoredWithAFractionIsRead(): void
    {
        $store = $this->store();
        $connection = $this->connection;
        self::assertInstanceOf(Connection::class, $connection);
        $connection->insert('phil_virtual_classroom_room', ['id' => 'room1', 'provider' => 'main', 'title' => 'Algebra', 'record' => 0, 'max_minutes' => 90.0, 'owner' => null, 'status' => 'scheduled', 'session_key' => null, 'created_at' => 1_700_000_000.5, 'opened_at' => null, 'ended_at' => null]);

        $room = $store->room(RoomId::fromString('room1'));

        self::assertNotNull($room);
        self::assertSame('1700000000.000000', $room->createdAt->format('U.u'));
        self::assertSame(90, $room->maxMinutes);
    }

    #[Override]
    protected function store(): ClassroomStore
    {
        $connection = $this->connection();
        $this->connection = $connection;
        $store = new DbalClassroomStore($connection);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        return $store;
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
