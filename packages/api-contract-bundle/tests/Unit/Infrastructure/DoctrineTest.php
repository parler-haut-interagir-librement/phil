<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\ApiContractBundle\Infrastructure\Doctrine\IdempotencySchemaListener;
use Phil\Idempotency\Store\IdempotencyTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdempotencySchemaListener::class)]
final class DoctrineTest extends TestCase
{
    public function testTheTableJoinsTheSchemaOfItsConnection(): void
    {
        $connection = $this->connection();
        $listener = new IdempotencySchemaListener($connection);
        $schema = $connection->createSchemaManager()->introspectSchema();

        $other = new GenerateSchemaEventArgs($this->entityManager($this->connection()), $schema);
        $listener->postGenerateSchema($other);
        self::assertSame($schema, $other->getSchema(), 'The schema of another connection is left as it is.');
        self::assertFalse($schema->hasTable(IdempotencyTable::NAME));

        $event = new GenerateSchemaEventArgs($this->entityManager($connection), $schema);
        $listener->postGenerateSchema($event);

        self::assertTrue($event->getSchema()->hasTable(IdempotencyTable::NAME));
        self::assertSame('phil_idempotency', IdempotencyTable::NAME);
    }

    /**
     * The listener builds the table for the platform of its connection: MySQL and MariaDB compare the scope and the key
     * in `utf8mb4_bin`, so that `Acme` and `acme` are two keys; PostgreSQL and SQLite compare bytes already.
     */
    #[TestWith(['pdo_mysql', '8.4', true])]
    #[TestWith(['pdo_mysql', '11.4.2-MariaDB', true])]
    #[TestWith(['pdo_pgsql', '17', false])]
    #[TestWith(['pdo_sqlite', '3.45', false])]
    public function testTheTableComparesScopesAndKeysByteForByte(string $driver, string $version, bool $binaryCollation): void
    {
        $connection = match ($driver) {
            'pdo_mysql' => DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => $version]),
            'pdo_pgsql' => DriverManager::getConnection(['driver' => 'pdo_pgsql', 'serverVersion' => $version]),
            default => DriverManager::getConnection(['driver' => 'pdo_sqlite', 'serverVersion' => $version, 'memory' => true]),
        };
        $event = new GenerateSchemaEventArgs($this->entityManager($connection), Schema::editor()->create());

        new IdempotencySchemaListener($connection)->postGenerateSchema($event);
        $sql = implode("\n", $event->getSchema()->toSql($connection->getDatabasePlatform()));

        self::assertStringContainsString('CREATE TABLE phil_idempotency (', $sql);

        if ($binaryCollation) {
            self::assertStringContainsString('scope VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, idempotency_key VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, fingerprint CHAR(64) NOT NULL', $sql);
        } else {
            self::assertStringContainsString('scope VARCHAR(255) NOT NULL, idempotency_key VARCHAR(255) NOT NULL, fingerprint CHAR(64) NOT NULL', $sql);
            self::assertStringNotContainsString('COLLATE', $sql);
        }
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    private function entityManager(Connection $connection): EntityManagerInterface
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }
}
