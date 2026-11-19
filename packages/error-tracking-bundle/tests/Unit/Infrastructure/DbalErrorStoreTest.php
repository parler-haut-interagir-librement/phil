<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\ExceptionRecord;
use Phil\ErrorTracking\Frame;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTracking\RequestSummary;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Infrastructure\Doctrine\DbalErrorStore;
use Phil\ErrorTrackingBundle\Infrastructure\Doctrine\ErrorTables;
use Phil\ErrorTrackingBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\RacingConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalErrorStore::class)]
#[CoversClass(ErrorTables::class)]
#[CoversClass(SchemaListener::class)]
final class DbalErrorStoreTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
    }

    public function testAGroupIsWrittenAndReadBack(): void
    {
        $store = new DbalErrorStore($this->connection);
        $group = $this->group('a', '2026-11-08 10:00:00', GroupStatus::Open, 'App\A::run (src/A.php:3)', '1.0.0');

        self::assertNull($store->find(str_repeat('a', 64)));
        $store->save($group);
        self::assertEquals($group, $store->find(str_repeat('a', 64)));

        $resolved = $group->seen($this->event('2026-11-09 10:00:00', '1.1.0'))->resolve();
        $store->save($resolved);
        self::assertEquals($resolved, $store->find(str_repeat('a', 64)));
        self::assertSame('1.1.0', $store->find(str_repeat('a', 64))?->resolvedRelease);
        self::assertSame(1, $store->countGroups(null));

        $bare = new ErrorGroup(str_repeat('b', 64), 'E', null, GroupStatus::Ignored, 3, new DateTimeImmutable('2026-11-01 10:00:00'), new DateTimeImmutable('2026-11-01 10:00:00'));
        $store->save($bare);
        self::assertEquals($bare, $store->find(str_repeat('b', 64)));
    }

    public function testTheInstantsAreWrittenAndReadInUtcWhateverTheTimeZoneOfPhp(): void
    {
        $zone = date_default_timezone_get();
        $store = new DbalErrorStore($this->connection);

        try {
            date_default_timezone_set('America/Toronto');
            $store->save($this->group('a', '2026-11-08T10:00:00-05:00'), $this->event('2026-11-08T10:00:00-05:00'));
            date_default_timezone_set('Asia/Tokyo');
            $group = $store->find(str_repeat('a', 64));
        } finally {
            date_default_timezone_set($zone);
        }

        self::assertSame('2026-11-08 15:00:00', $this->connection->fetchOne('SELECT last_seen_at FROM phil_error_group'));
        self::assertSame('2026-11-08 15:00:00', $this->connection->fetchOne('SELECT occurred_at FROM phil_error_event'));
        self::assertSame('2026-11-08T15:00:00+00:00', $group?->lastSeen->format(\DATE_ATOM));
        self::assertSame('UTC', $group->lastSeen->getTimezone()->getName());
        self::assertSame(0, $store->purge(new DateTimeImmutable('2026-11-08T09:59:59-05:00')), 'the purge compares UTC with UTC');
        self::assertSame(2, $store->purge(new DateTimeImmutable('2026-11-08T10:00:01-05:00')));
    }

    public function testTheRaceOfTwoFirstEventsEndsInAnUpdate(): void
    {
        $connection = $this->connection(RacingConnection::class);
        $this->assertInstanceOf(RacingConnection::class, $connection);
        $store = new DbalErrorStore($connection);
        $connection->racing = false;
        $other = $this->group('b', '2026-11-08 09:00:00');
        $store->save($other);
        $connection->racing = true;
        $group = $this->group('a', '2026-11-08 10:00:00');
        $store->save($group);

        self::assertEquals($group, $store->find(str_repeat('a', 64)));
        self::assertEquals($other, $store->find(str_repeat('b', 64)));
        self::assertSame(2, $store->countGroups(null));
    }

    public function testAKnownGroupIsUpdatedWithoutAnInsert(): void
    {
        $connection = $this->connection(RacingConnection::class);
        $this->assertInstanceOf(RacingConnection::class, $connection);
        $connection->racing = false;
        $store = new DbalErrorStore($connection);
        $store->save($this->group('a', '2026-11-08 10:00:00'));
        $store->save($this->group('b', '2026-11-08 10:00:00'));
        $store->save($this->group('a', '2026-11-09 10:00:00'));

        self::assertSame(2, $connection->inserts);
    }

    public function testEventsAreCappedPerGroup(): void
    {
        $store = new DbalErrorStore($this->connection, 3);
        $group = $this->group('a', '2026-11-08 10:00:00');

        for ($index = 1; $index <= 5; ++$index) {
            $store->save($group, $this->event(\sprintf('2026-11-08 10:00:0%d', $index), null, 'Event '.$index));
        }

        $store->save($this->group('b', '2026-11-08 10:00:00'), $this->event('2026-11-08 10:00:00', null, 'Other'));
        $store->save($group);

        self::assertSame(['E: Event 5', 'E: Event 4', 'E: Event 3'], array_map(static fn (ErrorEvent $event): string => $event->title(), $store->events(str_repeat('a', 64), 10)));
        self::assertSame(['E: Event 5', 'E: Event 4'], array_map(static fn (ErrorEvent $event): string => $event->title(), $store->events(str_repeat('a', 64), 2)));
        self::assertCount(1, $store->events(str_repeat('b', 64), 10));
        self::assertEquals(4, $this->connection->fetchOne('SELECT COUNT(*) FROM phil_error_event'));
        self::assertSame(50, DbalErrorStore::DEFAULT_EVENTS_PER_GROUP);
        self::assertCount(5, $this->filled(new DbalErrorStore($this->connection(), 5), 5));
        self::assertCount(4, $this->filled(new DbalErrorStore($this->connection(), 4), 5));
    }

    public function testAnEventWithFramesIsReadBack(): void
    {
        $store = new DbalErrorStore($this->connection);
        $event = new ErrorEvent(str_repeat('c', 32), new DateTimeImmutable('2026-11-08T10:00:00+00:00'), Source::Http, Severity::Error, [new ExceptionRecord('E', 'm', [new Frame('src/A.php', 3, 'App\A::run', true)])]);
        $store->save($this->group('a', '2026-11-08 10:00:00'), $event);

        self::assertEquals([$event], $store->events(str_repeat('a', 64), 1));
    }

    public function testAnEventIsKeptWhole(): void
    {
        $store = new DbalErrorStore($this->connection);
        $event = new ErrorEvent(str_repeat('c', 32), new DateTimeImmutable('2026-11-08T10:00:00.123456+00:00'), Source::Http, Severity::Fatal, [new ExceptionRecord('E', 'Ünïcode / slash', [])], '1.0', 'prod', new RequestSummary('GET', 'https://example.com/a', 'home', ['accept' => 'text/html'], '192.0.2.0'), ['ratio' => 1.0, 'n' => 2, 'ok' => true, 'none' => null]);
        $store->save($this->group('a', '2026-11-08 10:00:00'), $event);

        self::assertEquals([$event], $store->events(str_repeat('a', 64), 1));
        self::assertSame(str_repeat('c', 32), $this->connection->fetchOne('SELECT event_id FROM phil_error_event'));
        $payload = $this->connection->fetchOne('SELECT payload FROM phil_error_event');
        self::assertIsString($payload);
        self::assertStringContainsString('Ünïcode / slash', $payload);
        self::assertStringContainsString('"ratio":1.0', $payload);
    }

    public function testAnUnreadableEventIsSkipped(): void
    {
        $store = new DbalErrorStore($this->connection);
        $store->save($this->group('a', '2026-11-08 10:00:00'), $this->event('2026-11-08 10:00:00'));

        $deep = $this->event('2026-11-08 10:00:00')->toArray();
        $deep['exceptions'][0]['frames'] = [['file' => 'a.php', 'line' => 1, 'function' => null, 'in_app' => false, 'extra' => [1]]];

        foreach (['not json', '"text"', '{"id":"bad"}', str_repeat('[', 20).str_repeat(']', 20), (string) json_encode($deep)] as $payload) {
            $this->connection->insert('phil_error_event', ['event_id' => str_repeat('d', 32), 'fingerprint' => str_repeat('a', 64), 'occurred_at' => '2026-11-08 10:00:00', 'payload' => $payload]);
        }

        self::assertCount(1, $store->events(str_repeat('a', 64), 10));
    }

    public function testGroupsAreListedSeenLastFirst(): void
    {
        $store = new DbalErrorStore($this->connection);
        $store->save($this->group('a', '2026-11-08 10:00:00'));
        $store->save($this->group('b', '2026-11-09 10:00:00', GroupStatus::Resolved));
        $store->save($this->group('c', '2026-11-09 10:00:00'));
        $store->save($this->group('d', '2026-11-07 10:00:00', GroupStatus::Resolved));

        self::assertSame(['b', 'c', 'a', 'd'], $this->letters($store->groups(null, 10)));
        self::assertSame(['c', 'a'], $this->letters($store->groups(null, 2, 1)));
        self::assertSame(['b', 'c'], $this->letters($store->groups(null, 2)));
        self::assertSame(['b', 'd'], $this->letters($store->groups(GroupStatus::Resolved, 10)));
        self::assertSame(['d'], $this->letters($store->groups(GroupStatus::Resolved, 10, 1)));
        self::assertSame(4, $store->countGroups(null));
        self::assertSame(2, $store->countGroups(GroupStatus::Resolved));
        self::assertSame(0, $store->countGroups(GroupStatus::Ignored));
    }

    public function testThePurge(): void
    {
        $store = new DbalErrorStore($this->connection);
        $store->save($this->group('a', '2026-11-01 10:00:00'), $this->event('2026-11-01 10:00:00'));
        $store->save($this->group('b', '2026-11-08 10:00:00'), $this->event('2026-11-01 10:00:00'));
        $store->save($this->group('b', '2026-11-08 10:00:00'), $this->event('2026-11-08 10:00:00'));

        self::assertSame(3, $store->purge(new DateTimeImmutable('2026-11-05 00:00:00')));
        self::assertNull($store->find(str_repeat('a', 64)));
        self::assertCount(1, $store->events(str_repeat('b', 64), 10));
        self::assertSame(0, $store->purge(new DateTimeImmutable('2026-11-08 10:00:00')));
    }

    public function testTheTablesAreAddedOnce(): void
    {
        $schema = ErrorTables::configure(Schema::editor()->create());

        self::assertTrue($schema->hasTable(ErrorTables::GROUP));
        self::assertTrue($schema->hasTable(ErrorTables::EVENT));
        self::assertEquals($schema, ErrorTables::configure($schema));
        self::assertSame(['phil_error_group', 'phil_error_event'], [ErrorTables::GROUP, ErrorTables::EVENT]);

        $onlyEvents = ErrorTables::configure(Schema::editor()->create())->edit()->dropTableByUnquotedName(ErrorTables::GROUP)->create();
        self::assertTrue(ErrorTables::configure($onlyEvents)->hasTable(ErrorTables::GROUP));
    }

    public function testTheColumns(): void
    {
        $schema = ErrorTables::configure(Schema::editor()->create());
        $columns = [];

        $names = [
            ErrorTables::GROUP => ['fingerprint', 'title', 'culprit', 'status', 'times_seen', 'first_seen_at', 'last_seen_at', 'last_release', 'resolved_release'],
            ErrorTables::EVENT => ['id', 'event_id', 'fingerprint', 'occurred_at', 'payload'],
        ];

        foreach ($names as $table => $tableColumns) {
            self::assertCount(\count($tableColumns), $schema->getTable($table)->getColumns());

            foreach ($tableColumns as $name) {
                $column = $schema->getTable($table)->getColumn($name);
                $columns[$table.'.'.$name] = [$column->getTypeName(), $column->getLength(), $column->getNotnull(), $column->getFixed(), $column->getAutoincrement()];
            }
        }

        self::assertSame([
            'phil_error_group.fingerprint' => [Types::STRING, 64, true, true, false],
            'phil_error_group.title' => [Types::TEXT, null, true, false, false],
            'phil_error_group.culprit' => [Types::TEXT, null, false, false, false],
            'phil_error_group.status' => [Types::STRING, 16, true, false, false],
            'phil_error_group.times_seen' => [Types::BIGINT, null, true, false, false],
            'phil_error_group.first_seen_at' => [Types::DATETIME_UTC_IMMUTABLE, null, true, false, false],
            'phil_error_group.last_seen_at' => [Types::DATETIME_UTC_IMMUTABLE, null, true, false, false],
            'phil_error_group.last_release' => [Types::STRING, 128, false, false, false],
            'phil_error_group.resolved_release' => [Types::STRING, 128, false, false, false],
            'phil_error_event.id' => [Types::BIGINT, null, true, false, true],
            'phil_error_event.event_id' => [Types::STRING, 32, true, true, false],
            'phil_error_event.fingerprint' => [Types::STRING, 64, true, true, false],
            'phil_error_event.occurred_at' => [Types::DATETIME_UTC_IMMUTABLE, null, true, false, false],
            'phil_error_event.payload' => [Types::TEXT, null, true, false, false],
        ], $columns);
    }

    public function testTheSchemaListenerAddsTheTablesToItsConnectionOnly(): void
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($this->connection);
        $event = new GenerateSchemaEventArgs($entityManager, Schema::editor()->create());
        new SchemaListener($this->connection)->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(ErrorTables::GROUP));

        $other = new GenerateSchemaEventArgs($entityManager, Schema::editor()->create());
        new SchemaListener($this->connection())->postGenerateSchema($other);
        self::assertFalse($other->getSchema()->hasTable(ErrorTables::GROUP));
    }

    /**
     * @param class-string<Connection> $wrapper
     */
    private function connection(string $wrapper = Connection::class): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => $wrapper]);

        foreach (ErrorTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        return $connection;
    }

    /**
     * @return list<ErrorEvent>
     */
    private function filled(DbalErrorStore $store, int $events): array
    {
        for ($index = 0; $index < $events; ++$index) {
            $store->save($this->group('e', '2026-11-08 10:00:00'), $this->event('2026-11-08 10:00:00'));
        }

        return $store->events(str_repeat('e', 64), 100);
    }

    private function group(string $letter, string $seen, GroupStatus $status = GroupStatus::Open, ?string $culprit = null, ?string $release = null): ErrorGroup
    {
        return new ErrorGroup(str_repeat($letter, 64), 'RuntimeException: '.$letter, $culprit, $status, 2, new DateTimeImmutable('2026-11-01 08:00:00'), new DateTimeImmutable($seen), $release);
    }

    private function event(string $at, ?string $release = null, string $message = 'm'): ErrorEvent
    {
        return new ErrorEvent(str_repeat('e', 32), new DateTimeImmutable($at), Source::Http, Severity::Error, [new ExceptionRecord('E', $message)], $release);
    }

    /**
     * @param list<ErrorGroup> $groups
     *
     * @return list<string>
     */
    private function letters(array $groups): array
    {
        return array_map(static fn (ErrorGroup $group): string => $group->fingerprint[0], $groups);
    }
}
