<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Override;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseDriver;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseMiddleware;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseRouter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

#[CoversClass(TenantDatabaseMiddleware::class)]
#[CoversClass(TenantDatabaseDriver::class)]
final class TenantDatabaseMiddlewareTest extends TestCase
{
    public function testEachConnectionOpensWithTheParametersOfTheRouter(): void
    {
        $router = new TenantDatabaseRouter();
        $recorder = new class(new Driver()) extends AbstractDriverMiddleware {
            /** @var list<array<string, mixed>> */
            public array $opened = [];

            #[Override]
            public function connect(#[SensitiveParameter] array $params): Connection
            {
                $this->opened[] = $params;

                return parent::connect(['memory' => true]);
            }
        };

        $driver = new TenantDatabaseMiddleware($router)->wrap($recorder);
        self::assertInstanceOf(TenantDatabaseDriver::class, $driver);

        $driver->connect(['dbname' => 'shared']);
        $router->route(Placement::dedicated('tenant_retail'));
        $driver->connect(['dbname' => 'shared']);

        self::assertSame([['dbname' => 'shared'], ['dbname' => 'tenant_retail']], $recorder->opened);
    }

    public function testTheMiddlewareRunsInAConnection(): void
    {
        $router = new TenantDatabaseRouter();
        $configuration = new Configuration();
        $configuration->setMiddlewares([new TenantDatabaseMiddleware($router)]);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);

        self::assertEquals(1, $connection->fetchOne('SELECT 1'));
    }
}
