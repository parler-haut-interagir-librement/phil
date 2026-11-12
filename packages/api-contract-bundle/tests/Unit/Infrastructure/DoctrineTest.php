<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\ApiContractBundle\Infrastructure\Doctrine\IdempotencySchemaListener;
use Phil\Idempotency\Store\IdempotencyTable;
use PHPUnit\Framework\Attributes\CoversClass;
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
