<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\SamlSp\Store\DbalReplayGuard;
use Phil\SamlSp\Tests\Support\Stage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The database replay guard on a real server read from the environment: the second insert of an assertion is refused
 * by the server, of two connections only one wins, the purge forgets the expired rows, and an expiry given in another
 * time zone is kept in UTC.
 */
abstract class ServerReplayGuardTestCase extends TestCase
{
    private ?Connection $connection = null;

    private MockClock $clock;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

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

    public function testTheSecondInsertIsRefused(): void
    {
        $guard = $this->guard();
        $until = new DateTimeImmutable('2026-11-19 12:05:00 UTC');

        self::assertTrue($guard->firstUse('idp', 'a', $until));
        self::assertFalse($guard->firstUse('idp', 'a', $until));
        self::assertTrue($guard->firstUse('idp', 'A', $until));
        self::assertSame(2, $this->rows());
    }

    public function testOfTwoConnectionsOnlyOneWins(): void
    {
        $guard = $this->guard();
        $until = new DateTimeImmutable('2026-11-19 12:05:00 UTC');
        $other = $this->connect();
        $second = new DbalReplayGuard($other, $this->clock);

        $other->beginTransaction();
        self::assertTrue($second->firstUse('idp', 'a', $until));
        $other->commit();

        self::assertFalse($guard->firstUse('idp', 'a', $until));

        // A refusal inside a transaction leaves it usable.
        $other->beginTransaction();
        self::assertFalse($second->firstUse('idp', 'a', $until));
        self::assertTrue($second->firstUse('idp', 'b', $until));
        $other->commit();
        $other->close();

        self::assertFalse($guard->firstUse('idp', 'b', $until));
        self::assertSame(2, $this->rows());
    }

    public function testThePurgeForgetsTheExpiredAssertionsAndTheyMayBeUsedAgain(): void
    {
        $guard = $this->guard();
        $guard->firstUse('idp', 'a', new DateTimeImmutable('2026-11-19 12:05:00 UTC'));
        $guard->firstUse('idp', 'b', new DateTimeImmutable('2026-11-19 12:07:00 UTC'));

        self::assertSame(1, $guard->purge(new DateTimeImmutable('2026-11-19 12:05:00 UTC')));
        self::assertSame(1, $this->rows());

        $this->clock->modify('2026-11-19 12:06:00 UTC');
        self::assertTrue($guard->firstUse('idp', 'a', new DateTimeImmutable('2026-11-19 12:10:00 UTC')));
        self::assertFalse($guard->firstUse('idp', 'b', new DateTimeImmutable('2026-11-19 12:10:00 UTC')));
        $this->clock->modify('2026-11-19 12:07:00 UTC');
        self::assertTrue($guard->firstUse('idp', 'b', new DateTimeImmutable('2026-11-19 12:10:00 UTC')));
    }

    public function testAnExpiryWrittenInTorontoIsReadInUtc(): void
    {
        $guard = $this->guard();
        $toronto = new DateTimeImmutable('2026-11-19 07:05:00', new DateTimeZone('America/Toronto'));

        self::assertTrue($guard->firstUse('idp', 'a', $toronto));
        $read = $this->connection()->convertToPHPValue($this->connection()->fetchOne('SELECT expires_at FROM '.DbalReplayGuard::TABLE), Types::DATETIME_UTC_IMMUTABLE);

        self::assertInstanceOf(DateTimeImmutable::class, $read);
        self::assertSame('2026-11-19 12:05:00 UTC', $read->format('Y-m-d H:i:s e'));

        $this->clock->modify('2026-11-19 07:04:59 America/Toronto');
        self::assertFalse($guard->firstUse('idp', 'a', $toronto));
        $this->clock->modify('2026-11-19 12:05:00 UTC');
        self::assertTrue($guard->firstUse('idp', 'a', $toronto->modify('+1 minute')));
    }

    /**
     * The connection the guard of the test uses.
     */
    protected function connection(): Connection
    {
        return $this->connection ??= $this->connect();
    }

    /**
     * A guard on a table created anew from its schema.
     */
    protected function guard(): DbalReplayGuard
    {
        $connection = $this->connection();
        $guard = new DbalReplayGuard($connection, $this->clock);
        $connection->executeStatement('DROP TABLE IF EXISTS '.DbalReplayGuard::TABLE);
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

    private function connect(): Connection
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }
}
