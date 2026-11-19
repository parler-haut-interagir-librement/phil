<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\ClassroomTables;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\DbalClassroomStore;
use Phil\VirtualClassroomBundle\Tests\Unit\Infrastructure\StoreTestCase;

/**
 * Every promise of a store on a real server read from the environment, the byte-for-byte comparison of the
 * identifiers among them, and two connections reporting the same arrival: one stay.
 */
abstract class ServerStoreTestCase extends StoreTestCase
{
    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testTwoConnectionsReportingTheSameArrivalKeepOneStay(): void
    {
        $store = $this->store();
        $other = new DbalClassroomStore($this->connect());

        self::assertTrue($other->arrive(RoomId::fromString('room1'), ParticipantId::fromString('ada'), self::at(0)));
        self::assertFalse($store->arrive(RoomId::fromString('room1'), ParticipantId::fromString('ada'), self::at(0)));
        self::assertCount(1, $store->stays(RoomId::fromString('room1')));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    #[Override]
    protected function store(): ClassroomStore
    {
        $connection = $this->connection ??= $this->connect();
        $store = new DbalClassroomStore($connection);

        foreach (['room', 'stay', 'recording'] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.ClassroomTables::PREFIX.'_'.$table);
        }

        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        return $store;
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
