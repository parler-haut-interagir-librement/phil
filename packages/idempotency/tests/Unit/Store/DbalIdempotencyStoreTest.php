<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Override;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\Exception\ReservationLost;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\IdempotencyState;
use Phil\Idempotency\Store\DbalIdempotencyStore;
use Phil\Idempotency\Store\IdempotencyTable;
use Phil\Idempotency\StoredResponse;
use Phil\Idempotency\Tests\Unit\Fixture\ChangedRowsConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DbalIdempotencyStore::class)]
#[CoversClass(IdempotencyTable::class)]
final class DbalIdempotencyStoreTest extends IdempotencyStoreTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::connection();
        parent::setUp();
    }

    #[Override]
    protected function createStore(MockClock $clock): IdempotencyStore
    {
        return new DbalIdempotencyStore($this->connection, $clock);
    }

    public function testTheExpiriesAreWrittenInUtc(): void
    {
        [$scope, $key, $fingerprint] = $this->key();

        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 07:05', 'America/Toronto'));
        self::assertSame(['expires_at' => '2026-11-11 12:05:00', 'state' => 'in_progress'], $this->connection->fetchAssociative('SELECT expires_at, state FROM phil_idempotency'));

        $this->store->fail($reservation, $this->at('2026-11-12 07:00', 'America/Toronto'));
        self::assertSame(['expires_at' => '2026-11-12 12:00:00', 'state' => 'failed'], $this->connection->fetchAssociative('SELECT expires_at, state FROM phil_idempotency'));
    }

    public function testTheExpiryIsReadInUtcWhateverTheZoneOfPhp(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $this->store->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));
        $zone = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');

        try {
            self::assertSame($this->at('2026-11-11 12:05')->getTimestamp(), $this->store->find($scope, $key)?->expiresAt->getTimestamp());
        } finally {
            date_default_timezone_set($zone);
        }
    }

    public function testThePurgeForgetsTheExpiredKeysOnly(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $this->assertInstanceOf(DbalIdempotencyStore::class, $this->store);
        $this->store->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));
        $this->store->reserve($scope, IdempotencyKey::fromString('k2'), $fingerprint, $this->at('2026-11-11 12:10'));

        self::assertSame(0, $this->store->purge());
        $this->clock->modify('2026-11-11 12:05:00 UTC');
        self::assertSame(1, $this->store->purge());
        $left = $this->connection->fetchOne('SELECT COUNT(*) FROM phil_idempotency');
        self::assertIsInt($left);
        self::assertSame(1, $left);
    }

    public function testElsewhereAnExpiredRowIsDeletedFirst(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $this->store->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 13:00'));
        new ReflectionProperty(Connection::class, 'platform')->setValue($this->connection, new OraclePlatform());

        $this->clock->modify('2026-11-11 12:30 UTC');
        self::assertNull($this->store->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 14:00')), 'The unique violation of a plain insert is a lost reservation.');
        $this->clock->modify('2026-11-11 13:00 UTC');
        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 14:00'));
        self::assertSame(['expires_at' => '2026-11-11 14:00:00', 'token' => $reservation->token], $this->connection->fetchAssociative('SELECT expires_at, token FROM phil_idempotency'));
    }

    public function testOnlyACompletedRowHasAResponse(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $this->store->complete($this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05')), StoredResponse::of(201, [], 'made'), $this->at('2026-11-12 12:00'));

        $this->connection->executeStatement("UPDATE phil_idempotency SET state = 'failed'");

        $record = $this->store->find($scope, $key);

        self::assertSame([IdempotencyState::Failed, null], [$record?->state, $record?->response]);
    }

    /**
     * MySQL counts the rows an update changed, not the ones it matched: writing the same outcome again changes
     * nothing, and must not read as a lost reservation.
     */
    public function testAnUpdateThatChangesNothingIsNotALostReservation(): void
    {
        $connection = self::connection(changedRowsOnly: true);
        self::assertInstanceOf(ChangedRowsConnection::class, $connection);
        $this->connection = $connection;
        $this->store = new DbalIdempotencyStore($this->connection, $this->clock);
        [$scope, $key, $fingerprint] = $this->key();
        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));

        $connection->reads();
        $reads = [];

        foreach ([true, true, false, false] as $completes) {
            $completes
                ? $this->store->complete($reservation, StoredResponse::of(200, [], 'ok'), $this->at('2026-11-12 12:00'))
                : $this->store->fail($reservation, $this->at('2026-11-12 12:00'));
            $reads[] = $connection->reads();
        }

        self::assertSame([0, 1, 0, 1], $reads, 'Only an update that changed nothing reads its token back.');
        self::assertSame(IdempotencyState::Failed, $this->store->find($scope, $key)?->state);

        $this->connection->executeStatement('UPDATE phil_idempotency SET token = ?', [str_repeat('0', 32)]);

        $this->expectException(ReservationLost::class);
        $this->store->fail($reservation, $this->at('2026-11-12 12:00'));
    }

    public function testATokenOfAnotherKeyHoldsNothing(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $this->connection = self::connection(changedRowsOnly: true);
        $this->store = new DbalIdempotencyStore($this->connection, $this->clock);
        $held = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));

        foreach ([[IdempotencyScope::of('other'), $key], [$scope, IdempotencyKey::fromString('k2')]] as [$otherScope, $otherKey]) {
            $this->reserve($otherScope, $otherKey, $fingerprint, $this->at('2026-11-11 12:05'));
            $this->connection->executeStatement('UPDATE phil_idempotency SET token = ? WHERE scope = ? AND idempotency_key = ?', [$held->token, $otherScope->value, $otherKey->value]);
        }

        $this->store->release($scope, $key);

        $this->expectException(ReservationLost::class);
        $this->store->fail($held, $this->at('2026-11-12 12:00'));
    }

    public function testEachPlatformReservesItsOwnWay(): void
    {
        $into = 'INTO phil_idempotency (scope, idempotency_key, fingerprint, state, expires_at, token) VALUES (?, ?, ?, ?, ?, ?)';

        self::assertSame(['INSERT '.$into.' ON CONFLICT (scope, idempotency_key) DO NOTHING', 'INSERT IGNORE '.$into, 'INSERT IGNORE '.$into, 'INSERT OR IGNORE '.$into, 'INSERT '.$into], array_map(DbalIdempotencyStore::reservation(...), [new PostgreSQLPlatform(), new MySQLPlatform(), new MariaDBPlatform(), new SQLitePlatform(), new OraclePlatform()]));
    }

    public function testABinaryColumnIsReadFromAStream(): void
    {
        $text = new ReflectionMethod(DbalIdempotencyStore::class, 'text');
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, "\x00body");
        rewind($stream);

        self::assertSame(["\x00body", 'x', ''], [$text->invoke($this->store, $stream), $text->invoke($this->store, 'x'), $text->invoke($this->store, null)]);
    }

    public function testTheTableHasOneRowPerScopeAndKey(): void
    {
        $schema = IdempotencyTable::configure(self::connection(withTable: false)->createSchemaManager()->introspectSchema());
        $table = $schema->getTable(IdempotencyTable::NAME);

        self::assertSame(
            [[255, true, 'string'], [255, true, 'string'], [64, true, 'string'], [16, true, 'string'], [null, false, 'smallint'], [null, false, 'json'], [null, false, 'blob'], [null, true, 'datetime_immutable'], [32, true, 'string']],
            array_map(static fn (string $name): array => [$table->getColumn($name)->getLength(), $table->getColumn($name)->getNotnull(), $table->getColumn($name)->getTypeName()], ['scope', 'idempotency_key', 'fingerprint', 'state', 'status', 'headers', 'body', 'expires_at', 'token']),
        );
        self::assertTrue($table->getColumn('fingerprint')->getFixed());
        self::assertTrue($table->getColumn('token')->getFixed());
        self::assertSame(['scope', 'idempotency_key'], array_map(static fn (object $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertTrue($table->hasIndex('phil_idempotency_expires_at'));
        self::assertSame($schema, IdempotencyTable::configure($schema));
        self::assertCount(1, $schema->getTables());
    }

    public function testScopesAndKeysCompareByteForByteOnMysql(): void
    {
        $schema = IdempotencyTable::configure(self::connection(withTable: false)->createSchemaManager()->introspectSchema(), new MySQLPlatform());
        $table = $schema->getTable(IdempotencyTable::NAME);

        foreach (['scope', 'idempotency_key'] as $name) {
            self::assertSame(['utf8mb4', 'utf8mb4_bin'], [$table->getColumn($name)->getCharset(), $table->getColumn($name)->getCollation()]);
        }

        $elsewhere = IdempotencyTable::configure(self::connection(withTable: false)->createSchemaManager()->introspectSchema(), new SQLitePlatform())->getTable(IdempotencyTable::NAME);
        self::assertSame([null, null], [$elsewhere->getColumn('scope')->getCharset(), $elsewhere->getColumn('scope')->getCollation()]);
    }

    /**
     * @param bool $changedRowsOnly whether updates report the rows they changed, as MySQL does, rather than the ones
     *                              they matched
     */
    public static function connection(string $path = '', bool $withTable = true, bool $changedRowsOnly = false): Connection
    {
        $params = '' === $path ? ['driver' => 'pdo_sqlite', 'memory' => true] : ['driver' => 'pdo_sqlite', 'path' => $path];
        $connection = DriverManager::getConnection($changedRowsOnly ? [...$params, 'wrapperClass' => ChangedRowsConnection::class] : $params);

        if ($withTable) {
            $schema = IdempotencyTable::configure($connection->createSchemaManager()->introspectSchema());

            foreach ($connection->getDatabasePlatform()->getCreateTablesSQL($schema->getTables()) as $sql) {
                $connection->executeStatement($sql);
            }
        }

        return $connection;
    }
}
