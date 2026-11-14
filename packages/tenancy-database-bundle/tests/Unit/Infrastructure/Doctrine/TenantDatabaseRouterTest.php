<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Doctrine;

use Override;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyDatabaseBundle\Application\Contract\TenantCredentials;
use Phil\TenancyDatabaseBundle\Domain\ValueObject\DatabaseCredentials;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseRouter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantDatabaseRouter::class)]
final class TenantDatabaseRouterTest extends TestCase
{
    private const array SHARED = ['driver' => 'pdo_pgsql', 'host' => 'db', 'port' => 5432, 'user' => 'app', 'password' => 'app-secret', 'dbname' => 'app'];

    public function testTheSharedDatabaseKeepsTheParameters(): void
    {
        $router = new TenantDatabaseRouter();

        self::assertTrue($router->current()->isShared());
        $this->assertParameters(self::SHARED, $router->parameters(self::SHARED));
    }

    public function testADedicatedDatabaseReplacesTheNameOnly(): void
    {
        $router = new TenantDatabaseRouter();
        $router->route(Placement::dedicated('tenant_retail'));

        self::assertSame('tenant_retail', $router->current()->database);
        $this->assertParameters([...self::SHARED, 'dbname' => 'tenant_retail'], $router->parameters(self::SHARED));
    }

    public function testTheCredentialsOfADedicatedDatabaseReplaceTheAccountAndTheServer(): void
    {
        $router = new TenantDatabaseRouter($this->credentials(['tenant_retail' => new DatabaseCredentials('retail', 'retail-secret', 'other', 6432)]));
        $router->route(Placement::dedicated('tenant_retail'));

        $this->assertParameters(['driver' => 'pdo_pgsql', 'host' => 'other', 'port' => 6432, 'user' => 'retail', 'password' => 'retail-secret', 'dbname' => 'tenant_retail'], $router->parameters(self::SHARED));
    }

    public function testCredentialsWithoutAServerKeepTheServerOfTheSharedConnection(): void
    {
        $router = new TenantDatabaseRouter($this->credentials(['tenant_retail' => new DatabaseCredentials('retail', 'retail-secret')]));
        $router->route(Placement::dedicated('tenant_retail'));

        $this->assertParameters(['driver' => 'pdo_pgsql', 'host' => 'db', 'port' => 5432, 'user' => 'retail', 'password' => 'retail-secret', 'dbname' => 'tenant_retail'], $router->parameters(self::SHARED));
    }

    public function testADatabaseWithoutCredentialsKeepsTheAccountOfTheSharedConnection(): void
    {
        $router = new TenantDatabaseRouter($this->credentials([]));
        $router->route(Placement::dedicated('tenant_wholesale'));

        $this->assertParameters([...self::SHARED, 'dbname' => 'tenant_wholesale'], $router->parameters(self::SHARED));
    }

    public function testTheSharedDatabaseNeverAsksForCredentials(): void
    {
        $router = new TenantDatabaseRouter($this->credentials(['tenant_retail' => new DatabaseCredentials('retail', 'retail-secret')]));

        $this->assertParameters(self::SHARED, $router->parameters(self::SHARED));
    }

    public function testAPinnedConnectionKeepsItsDatabaseWhateverTheTenant(): void
    {
        $router = new TenantDatabaseRouter();
        $pinned = $router->pinned(Placement::dedicated('tenant_retail'), [...self::SHARED, 'driverOptions' => ['keep' => 1]]);

        $this->assertParameters([...self::SHARED, 'driverOptions' => ['keep' => 1, TenantDatabaseRouter::PINNED => true], 'dbname' => 'tenant_retail'], $pinned);

        $router->route(Placement::dedicated('tenant_wholesale'));
        $this->assertParameters([...self::SHARED, 'driverOptions' => ['keep' => 1], 'dbname' => 'tenant_retail'], $router->parameters($pinned), 'The pin is removed before the driver reads its options.');
    }

    public function testAPinToTheSharedDatabaseKeepsTheSharedName(): void
    {
        $router = new TenantDatabaseRouter();
        $router->route(Placement::dedicated('tenant_wholesale'));

        $this->assertParameters([...self::SHARED, 'driverOptions' => []], $router->parameters($router->pinned(Placement::shared(), self::SHARED)));
    }

    public function testAnOptionThatIsNotTrueDoesNotPin(): void
    {
        $router = new TenantDatabaseRouter();
        $router->route(Placement::dedicated('tenant_retail'));

        self::assertSame('tenant_retail', $router->parameters([...self::SHARED, 'driverOptions' => [TenantDatabaseRouter::PINNED => 1]])['dbname'] ?? null);
    }

    public function testAResetReturnsToTheSharedDatabase(): void
    {
        $router = new TenantDatabaseRouter();
        $router->route(Placement::dedicated('tenant_retail'));
        $router->reset();

        self::assertTrue($router->current()->isShared());
        $this->assertParameters(self::SHARED, $router->parameters(self::SHARED));
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     */
    private function assertParameters(array $expected, array $actual, string $message = ''): void
    {
        self::assertSame($expected, $actual, $message);
    }

    /**
     * @param array<string, DatabaseCredentials> $credentials
     */
    private function credentials(array $credentials): TenantCredentials
    {
        return new readonly class($credentials) implements TenantCredentials {
            /**
             * @param array<string, DatabaseCredentials> $credentials
             */
            public function __construct(
                private array $credentials,
            ) {
            }

            #[Override]
            public function credentialsFor(string $database): ?DatabaseCredentials
            {
                return $this->credentials[$database] ?? null;
            }
        };
    }
}
