<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Store;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use Override;
use Phil\SamlSp\Store\DbalReplayGuard;
use Phil\SamlSp\Tests\Support\SqlRecorder;
use Phil\SamlSp\Tests\Support\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The database replay guard on SQLite in memory; the same promises run on MySQL and PostgreSQL in the Integration
 * suite.
 */
#[CoversClass(DbalReplayGuard::class)]
final class DbalReplayGuardTest extends TestCase
{
    private ?Connection $connection = null;

    private MockClock $clock;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = Stage::clock('2026-11-19 12:00:00 UTC');
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    public function testTheSecondUseIsRefused(): void
    {
        $guard = $this->guard();
        $until = new DateTimeImmutable('2026-11-19 12:05:00 UTC');

        self::assertTrue($guard->firstUse('idp', 'a', $until));
        self::assertFalse($guard->firstUse('idp', 'a', $until));
        self::assertFalse($guard->firstUse('idp', 'a', $until->modify('+1 hour')));
        self::assertTrue($guard->firstUse('other', 'a', $until));
        self::assertTrue($guard->firstUse('idp', 'b', $until));
        self::assertSame(3, $this->rows());
    }

    public function testTheInsertIsPlainOutsidePostgreSqlAndAViolationIsTheRefusal(): void
    {
        $recorder = new SqlRecorder();
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], new Configuration()->setMiddlewares([$recorder]));
        $guard = $this->guard();
        $until = new DateTimeImmutable('2026-11-19 12:05:00 UTC');

        self::assertTrue($guard->firstUse('idp', 'a', $until));
        self::assertFalse($guard->firstUse('idp', 'a', $until));
        self::assertSame(array_fill(0, 2, 'INSERT INTO phil_saml_used_assertion (assertion, expires_at) VALUES (?, ?)'), $recorder->startingWith('INSERT'));
    }

    public function testOnPostgreSqlTheInsertDoesNothingOnAConflictAndCountsNoRow(): void
    {
        $recorder = new SqlRecorder(postgreSql: true);
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], new Configuration()->setMiddlewares([$recorder]));
        $this->connection->executeStatement('CREATE TABLE phil_saml_used_assertion (assertion CHAR(64) NOT NULL PRIMARY KEY, expires_at DATETIME NOT NULL)');

        $guard = new DbalReplayGuard($this->connection, $this->clock);
        $until = new DateTimeImmutable('2026-11-19 12:05:00 UTC');

        self::assertTrue($guard->firstUse('idp', 'a', $until));
        self::assertFalse($guard->firstUse('idp', 'a', $until));
        self::assertSame(array_fill(0, 2, 'INSERT INTO phil_saml_used_assertion (assertion, expires_at) VALUES (?, ?) ON CONFLICT (assertion) DO NOTHING'), $recorder->startingWith('INSERT'));
        self::assertSame(1, $this->rows());
    }

    public function testAnotherGuardOnTheSameTableRefusesTheAssertion(): void
    {
        $until = new DateTimeImmutable('2026-11-19 12:05:00 UTC');

        self::assertTrue($this->guard()->firstUse('idp', 'a', $until));
        self::assertFalse(new DbalReplayGuard($this->connection(), $this->clock)->firstUse('idp', 'a', $until));
    }

    public function testAnExpiredAssertionMayBeUsedAgain(): void
    {
        $guard = $this->guard();
        $until = new DateTimeImmutable('2026-11-19 12:05:00 UTC');

        self::assertTrue($guard->firstUse('idp', 'a', $until));
        $this->clock->modify('2026-11-19 12:04:59 UTC');
        self::assertFalse($guard->firstUse('idp', 'a', $until));
        $this->clock->modify('2026-11-19 12:05:00 UTC');
        self::assertTrue($guard->firstUse('idp', 'a', $until->modify('+5 minutes')));
        self::assertFalse($guard->firstUse('idp', 'a', $until));
        self::assertSame(1, $this->rows());
    }

    public function testTheExpiryOfAnotherAssertionLeavesThisOneAlone(): void
    {
        $guard = $this->guard();

        self::assertTrue($guard->firstUse('idp', 'old', new DateTimeImmutable('2026-11-19 12:01:00 UTC')));
        self::assertTrue($guard->firstUse('idp', 'new', new DateTimeImmutable('2026-11-19 12:30:00 UTC')));
        $this->clock->modify('2026-11-19 12:10:00 UTC');

        self::assertTrue($guard->firstUse('idp', 'third', new DateTimeImmutable('2026-11-19 12:30:00 UTC')));
        self::assertSame(3, $this->rows());
        self::assertFalse($guard->firstUse('idp', 'new', new DateTimeImmutable('2026-11-19 12:30:00 UTC')));
    }

    public function testThePurgeForgetsTheExpiredAssertionsOnly(): void
    {
        $guard = $this->guard();
        $guard->firstUse('idp', 'a', new DateTimeImmutable('2026-11-19 12:05:00 UTC'));
        $guard->firstUse('idp', 'b', new DateTimeImmutable('2026-11-19 12:06:00 UTC'));
        $guard->firstUse('idp', 'c', new DateTimeImmutable('2026-11-19 12:07:00 UTC'));

        self::assertSame(0, $guard->purge(new DateTimeImmutable('2026-11-19 12:04:59 UTC')));
        self::assertSame(2, $guard->purge(new DateTimeImmutable('2026-11-19 12:06:00 UTC')));
        self::assertSame(1, $this->rows());
        self::assertFalse($guard->firstUse('idp', 'c', new DateTimeImmutable('2026-11-19 12:07:00 UTC')));
        self::assertSame(1, $guard->purge(new DateTimeImmutable('2026-11-19 13:00:00 UTC')));
    }

    public function testTheExpiryIsWrittenInUtcWhateverTheTimeZoneGiven(): void
    {
        $guard = $this->guard();
        $toronto = new DateTimeImmutable('2026-11-19 07:05:00', new DateTimeZone('America/Toronto'));

        self::assertTrue($guard->firstUse('idp', 'a', $toronto));
        self::assertSame('2026-11-19 12:05:00', $this->connection()->fetchOne('SELECT expires_at FROM '.DbalReplayGuard::TABLE));
        $read = $this->connection()->convertToPHPValue($this->connection()->fetchOne('SELECT expires_at FROM '.DbalReplayGuard::TABLE), Types::DATETIME_UTC_IMMUTABLE);
        self::assertInstanceOf(DateTimeImmutable::class, $read);
        self::assertSame('UTC', $read->getTimezone()->getName());
        self::assertSame($toronto->getTimestamp(), $read->getTimestamp());

        $this->clock->modify('2026-11-19 07:04:59 America/Toronto');
        self::assertFalse($guard->firstUse('idp', 'a', $toronto));
        $this->clock->modify('2026-11-19 12:05:00 UTC');
        self::assertTrue($guard->firstUse('idp', 'a', $toronto->modify('+1 minute')));
    }

    /**
     * @return iterable<string, array{array{string, string}, array{string, string}}>
     */
    public static function distinctAssertions(): iterable
    {
        yield 'the split of the issuer and the ID' => [['a', 'bc'], ['ab', 'c']];
        yield 'a colon hides in the issuer' => [['0', 'aaaaaaaaa:z'], [':aaaaaaaaa', 'z']];
        yield 'two issuers of one length' => [['idp-1', 'a'], ['idp-2', 'a']];
        yield 'case' => [['idp', 'A'], ['idp', 'a']];
    }

    /**
     * @param array{string, string} $first
     * @param array{string, string} $second
     */
    #[DataProvider('distinctAssertions')]
    public function testTwoAssertionsNeverShareAKey(array $first, array $second): void
    {
        $guard = $this->guard();
        $until = new DateTimeImmutable('2026-11-19 12:05:00 UTC');

        self::assertNotSame(DbalReplayGuard::key(...$first), DbalReplayGuard::key(...$second));
        self::assertTrue($guard->firstUse($first[0], $first[1], $until));
        self::assertTrue($guard->firstUse($second[0], $second[1], $until));
    }

    public function testTheKeyIsTheSha256OfTheLengthTheIssuerAndTheId(): void
    {
        self::assertSame(hash('sha256', '3:idp_abc'), DbalReplayGuard::key('idp', '_abc'));
        self::assertSame(DbalReplayGuard::KEY_LENGTH, \strlen(DbalReplayGuard::key(str_repeat('i', 2000), str_repeat('a', 2000))));
    }

    public function testTheSchemaHoldsTheTableOnce(): void
    {
        $guard = new DbalReplayGuard($this->connection(), $this->clock, 'acme_used_assertion');
        $schema = $guard->configureSchema(Schema::editor()->create());
        $table = $schema->getTable('acme_used_assertion');

        self::assertSame(['assertion'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(64, $table->getColumn('assertion')->getLength());
        self::assertTrue($table->getColumn('assertion')->getFixed());
        self::assertTrue($table->getColumn('expires_at')->getNotnull());
        self::assertSame(Types::DATETIME_UTC_IMMUTABLE, $table->getColumn('expires_at')->getTypeName());
        self::assertSame(['expires_at'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), array_map(static fn (IndexedColumn $column): UnqualifiedName => $column->getColumnName(), $table->getIndex('acme_used_assertion_expires_at')->getIndexedColumns())));
        self::assertNull($table->getColumn('assertion')->getCollation());
        self::assertSame($schema, $guard->configureSchema($schema));
        self::assertCount(1, $schema->getTables());
    }

    public function testTheKeyIsBinaryOnMySqlAndMariaDb(): void
    {
        foreach (['8.4.0', '11.4.0-MariaDB'] as $version) {
            $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => $version]);
            $column = new DbalReplayGuard($connection, $this->clock)->configureSchema(Schema::editor()->create())->getTable(DbalReplayGuard::TABLE)->getColumn('assertion');

            self::assertSame('ascii', $column->getCharset(), $version);
            self::assertSame('ascii_bin', $column->getCollation(), $version);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTables(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1table'];
        yield 'uppercase' => ['Table'];
        yield 'a quote' => ['t"; DROP TABLE x; --'];
        yield 'a newline after' => ["table\n"];
        yield 'too long' => [str_repeat('t', 64)];
    }

    #[DataProvider('invalidTables')]
    public function testATableNameIsRefusedUnlessPlain(string $table): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a table name', $table));

        new DbalReplayGuard($this->connection(), $this->clock, $table);
    }

    public function testTheLongestTableNameIsAccepted(): void
    {
        $table = '_'.str_repeat('a', 61).'9';
        $guard = new DbalReplayGuard($this->connection(), $this->clock, $table);

        self::assertTrue($guard->configureSchema(Schema::editor()->create())->hasTable($table));
    }

    private function guard(): DbalReplayGuard
    {
        $connection = $this->connection();
        $guard = new DbalReplayGuard($connection, $this->clock);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($guard->configureSchema($manager->introspectSchema()));

        return $guard;
    }

    private function rows(): int
    {
        $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM '.DbalReplayGuard::TABLE);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function connection(): Connection
    {
        return $this->connection ??= DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
