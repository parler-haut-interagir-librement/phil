<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Override;
use Phil\ApiContractBundle\Infrastructure\Doctrine\IdempotencySchemaListener;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyReservation;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\Store\DbalIdempotencyStore;
use Phil\Idempotency\Store\IdempotencyTable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The table the schema listener adds, created on a real server read from the environment: scopes and keys that differ
 * only by case or accent are distinct rows, so that no caller is answered with another caller's response.
 */
abstract class ServerSchemaTestCase extends TestCase
{
    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testKeysAndScopesThatDifferByCaseAreTwoKeys(): void
    {
        $connection = $this->connect();
        $this->connection = $connection;
        $connection->executeStatement('DROP TABLE IF EXISTS '.IdempotencyTable::NAME);

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $event = new GenerateSchemaEventArgs($entityManager, Schema::editor()->create());

        new IdempotencySchemaListener($connection)->postGenerateSchema($event);

        foreach ($event->getSchema()->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $store = new DbalIdempotencyStore($connection, new MockClock('2026-11-17 12:00:00 UTC'));
        $until = new DateTimeImmutable('2026-11-17 13:00:00 UTC');
        $fingerprint = PayloadFingerprint::ofParts('a');

        foreach ([['caller:Acme', 'Key'], ['caller:acme', 'Key'], ['caller:acme', 'key'], ['caller:acmé', 'key']] as [$scope, $key]) {
            self::assertInstanceOf(IdempotencyReservation::class, $store->reserve(IdempotencyScope::of($scope), IdempotencyKey::fromString($key), $fingerprint, $until), \sprintf('%s and %s were taken by another row.', $scope, $key));
        }

        self::assertNull($store->reserve(IdempotencyScope::of('caller:acme'), IdempotencyKey::fromString('key'), $fingerprint, $until));
        $count = $connection->fetchOne('SELECT COUNT(*) FROM '.IdempotencyTable::NAME);
        self::assertIsNumeric($count);
        self::assertSame(4, (int) $count);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->executeStatement('DROP TABLE IF EXISTS '.IdempotencyTable::NAME);
        $this->connection?->close();
        $this->connection = null;
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
