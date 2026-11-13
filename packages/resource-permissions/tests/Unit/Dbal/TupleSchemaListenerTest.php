<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\ResourcePermissions\Dbal\DbalTupleStore;
use Phil\ResourcePermissions\Dbal\TupleSchemaListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(TupleSchemaListener::class)]
final class TupleSchemaListenerTest extends TestCase
{
    public function testTheTableJoinsTheSchemaOfTheStoresConnectionOnly(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $listener = new TupleSchemaListener($connection, new DbalTupleStore($connection, new MockClock()));

        $own = new GenerateSchemaEventArgs($this->entityManager($connection), $connection->createSchemaManager()->introspectSchema());
        $listener->postGenerateSchema($own);
        self::assertTrue($own->getSchema()->hasTable(DbalTupleStore::TABLE));

        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $foreign = new GenerateSchemaEventArgs($this->entityManager($other), $other->createSchemaManager()->introspectSchema());
        $listener->postGenerateSchema($foreign);
        self::assertFalse($foreign->getSchema()->hasTable(DbalTupleStore::TABLE));
    }

    private function entityManager(Connection $connection): EntityManagerInterface
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }
}
