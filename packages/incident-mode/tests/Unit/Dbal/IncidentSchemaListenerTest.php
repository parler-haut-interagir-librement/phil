<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Unit\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\IncidentMode\Dbal\IncidentSchemaListener;
use Phil\IncidentMode\Dbal\IncidentTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IncidentSchemaListener::class)]
#[CoversClass(IncidentTables::class)]
final class IncidentSchemaListenerTest extends TestCase
{
    public function testTheTableJoinsTheSchemaOfTheStoreConnectionOnly(): void
    {
        $connection = $this->connection();
        $listener = new IncidentSchemaListener($connection);
        $schema = Schema::editor()->create();

        $other = new GenerateSchemaEventArgs($this->entityManager($this->connection()), $schema);
        $listener->postGenerateSchema($other);

        self::assertSame($schema, $other->getSchema());

        $event = new GenerateSchemaEventArgs($this->entityManager($connection), $schema);
        $listener->postGenerateSchema($event);

        self::assertTrue($event->getSchema()->hasTable(IncidentTables::ACTIVATIONS));
    }

    private function entityManager(Connection $connection): EntityManagerInterface
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
