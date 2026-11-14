<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Database;

use Doctrine\DBAL\DriverManager;
use LogicException;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyDatabaseBundle\Infrastructure\Database\TenantDatabaseCreator;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseRouter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantDatabaseCreator::class)]
final class TenantDatabaseCreatorTest extends TestCase
{
    public function testTheSharedDatabaseIsNotCreatedForATenant(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The shared database is created with the application, not for a tenant.');

        $this->creator(new TenantDatabaseRouter())->create(Placement::shared());
    }

    public function testADatabaseIsCreatedInNoTenantOnly(): void
    {
        $router = new TenantDatabaseRouter();
        $router->route(Placement::dedicated('tenant_wholesale'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The connection is in the database "tenant_wholesale": create a tenant\'s database in no tenant.');

        $this->creator($router)->create(Placement::dedicated('tenant_retail'));
    }

    private function creator(TenantDatabaseRouter $router): TenantDatabaseCreator
    {
        return new TenantDatabaseCreator(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $router);
    }
}
