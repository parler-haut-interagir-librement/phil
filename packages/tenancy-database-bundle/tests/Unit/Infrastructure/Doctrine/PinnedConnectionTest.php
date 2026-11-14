<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\PinnedConnection;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseRouter;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\RecordingDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @phpstan-import-type Params from DriverManager
 */
#[CoversClass(PinnedConnection::class)]
final class PinnedConnectionTest extends TestCase
{
    public function testAConnectionPinnedToADatabaseWithTheConfigurationOfTheManagedOne(): void
    {
        $managed = $this->managed(['host' => 'db', 'dbname' => 'app', 'user' => 'app']);
        $router = new TenantDatabaseRouter();
        $router->route(Placement::dedicated('tenant_retail'));

        $pinned = PinnedConnection::open($managed, $router, Placement::dedicated('tenant_moved'));

        self::assertNotSame($managed, $pinned);
        self::assertFalse($pinned->isConnected(), 'Opened at its first query only.');
        self::assertSame($managed->getConfiguration(), $pinned->getConfiguration());

        $parameters = $this->open($pinned);
        self::assertSame('tenant_moved', $parameters['dbname'] ?? null);
        self::assertSame('db', $parameters['host'] ?? null);
        self::assertSame([TenantDatabaseRouter::PINNED => true], $parameters['driverOptions'] ?? null);
    }

    public function testTheSharedDatabaseWhateverTheActiveTenant(): void
    {
        $router = new TenantDatabaseRouter();
        $router->route(Placement::dedicated('tenant_retail'));

        $parameters = $this->open(PinnedConnection::shared($this->managed(['host' => 'db', 'dbname' => 'app']), $router));

        self::assertSame('app', $parameters['dbname'] ?? null);
        self::assertSame([TenantDatabaseRouter::PINNED => true], $parameters['driverOptions'] ?? null);
    }

    public function testAPrimaryAndItsReplicasGiveThePrimary(): void
    {
        $managed = $this->managed([
            'dbname' => 'app',
            'user' => 'app',
            'primary' => ['host' => 'primary', 'user' => 'writer'],
            'replica' => [['host' => 'replica']],
        ]);

        $parameters = $this->open(PinnedConnection::shared($managed, new TenantDatabaseRouter()));

        self::assertSame('primary', $parameters['host'] ?? null);
        self::assertSame('writer', $parameters['user'] ?? null);
        self::assertSame('app', $parameters['dbname'] ?? null);
        self::assertArrayNotHasKey('primary', $parameters);
        self::assertArrayNotHasKey('replica', $parameters);
    }

    /**
     * A connection whose driver records the parameters it is opened with, and opens nothing.
     *
     * @param Params $parameters
     */
    private function managed(array $parameters): Connection
    {
        $configuration = new Configuration();
        $configuration->setMiddlewares([new RecordingDriver()]);

        return DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0', ...$parameters], $configuration);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function open(Connection $connection): array
    {
        try {
            $connection->fetchOne('SELECT 1');
            self::fail('The connection opened.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(RecordingDriver::REFUSAL, $runtimeException->getMessage());
        }

        $driver = $connection->getConfiguration()->getMiddlewares()[0] ?? null;
        self::assertInstanceOf(RecordingDriver::class, $driver);
        self::assertCount(1, $driver->opened);

        return $driver->opened[0];
    }
}
