<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\UsageMeter\Dbal\DbalUsageStore;
use Phil\UsageMeter\Dbal\UsageSchemaListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UsageSchemaListener::class)]
final class UsageSchemaListenerTest extends TestCase
{
    public function testTheTablesJoinTheSchemaOfTheStoreConnectionOnly(): void
    {
        $connection = $this->connection();
        $listener = new UsageSchemaListener($connection, new DbalUsageStore($connection, 'quota_counter', 'quota_key'));
        $schema = new Schema();

        $other = new GenerateSchemaEventArgs($this->entityManager($this->connection()), $schema);
        $listener->postGenerateSchema($other);

        self::assertSame($schema, $other->getSchema());
        self::assertFalse($schema->hasTable('quota_counter'));

        $event = new GenerateSchemaEventArgs($this->entityManager($connection), $schema);
        $listener->postGenerateSchema($event);

        self::assertTrue($event->getSchema()->hasTable('quota_counter'));
        self::assertTrue($event->getSchema()->hasTable('quota_key'));
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
