<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Phil\TenancyBundle\Application\Contract\TenantPlacement;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\PlacedTenants;
use Phil\TenancyDatabaseBundle\Application\Contract\TenantDatabaseAvailability;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreTables;
use Phil\TenancyDatabaseBundle\PhilTenancyDatabaseBundle;
use Phil\TenancyDatabaseBundle\Tests\Integration\App\TestKernel;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The durable stores on a real server: the placements and the suspensions in the tables of the shared database, read
 * through a connection of their own that never follows the tenant.
 */
trait StoredPlacementsTests
{
    public function testTheTablesOfTheStoresAreCreatedWithTheSchema(): void
    {
        foreach ([TestKernel::SHARED, 'phil_tdb_wholesale'] as $database) {
            $connection = $this->onDatabase($database);

            try {
                self::assertTrue($connection->createSchemaManager()->tablesExist([StoreTables::PLACEMENTS, StoreTables::SUSPENSIONS]), $database);
            } finally {
                $connection->close();
            }
        }
    }

    public function testThePlacementsAreReadFromTheSharedDatabaseWhateverTheActiveTenant(): void
    {
        $this->seed();
        $placement = $this->service(TenantPlacement::class, TenantPlacement::class);

        $this->in('wholesale', function () use ($placement): void {
            self::assertSame(['W-1'], $this->entityManager()->getConnection()->fetchFirstColumn('SELECT label FROM invoice'));
            // The table of the dedicated database is empty: read there, the tenant would be shared.
            self::assertTrue($placement->placementOf(TenantId::fromString('wholesale'))->equals(Placement::dedicated('phil_tdb_wholesale')));
        });

        self::assertInstanceOf(PlacedTenants::class, $placement);
        self::assertSame(['newcomer', 'wholesale'], array_map(static fn (TenantId $tenant): string => $tenant->value, [...$placement->placedTenants()]));

        $response = $this->storedRequest('/both', 'retail.test');
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testAMoveRecordsItsPlacementInTheTable(): void
    {
        $this->seed();

        $there = $this->storedCommand('phil:tenancy:database:move');
        self::assertSame(0, $there->execute(['tenant' => 'retail', 'target' => self::MOVED]), $there->getDisplay());
        self::assertSame([['tenant' => 'retail', 'database_name' => self::MOVED]], $this->placementRows('retail'));
        self::assertSame(['R-1', 'R-2'], $this->labelsIn(self::MOVED, 'retail'));
        self::assertSame(200, $this->storedRequest('/invoices', 'retail.test')->getStatusCode());

        $back = $this->storedCommand('phil:tenancy:database:move');
        self::assertSame(0, $back->execute(['tenant' => 'retail', 'target' => 'shared']), $back->getDisplay());
        self::assertSame([['tenant' => 'retail', 'database_name' => null]], $this->placementRows('retail'));
        self::assertSame(['R-1', 'R-2'], $this->labelsIn(TestKernel::SHARED, 'retail'));
        self::assertSame([], $this->labelsIn(self::MOVED, 'retail'));
    }

    public function testASuspensionSurvivesAClearedCacheAndTheEndOfTheUnitOfWork(): void
    {
        $this->seed();
        $availability = $this->service(TenantDatabaseAvailability::class, TenantDatabaseAvailability::class);
        $availability->suspendDatabase(Placement::dedicated('phil_tdb_wholesale'));
        $availability->suspendTenant(TenantId::fromString('retail'));

        $this->service('cache.app', CacheItemPoolInterface::class)->clear();
        $this->service('services_resetter', ResetInterface::class)->reset();

        self::assertFalse($this->service(PhilTenancyDatabaseBundle::STORE_CONNECTION, Connection::class)->isConnected(), 'Closed at the end of the unit of work.');
        self::assertSame(503, $this->storedRequest('/invoices', 'wholesale.test')->getStatusCode());
        self::assertSame(503, $this->storedRequest('/invoices', 'retail.test')->getStatusCode());

        $shared = $this->onDatabase(TestKernel::SHARED);

        try {
            self::assertSame(
                [['kind' => 'database', 'subject' => 'phil_tdb_wholesale'], ['kind' => 'tenant', 'subject' => 'retail']],
                $shared->fetchAllAssociative(\sprintf('SELECT kind, subject FROM %s ORDER BY kind, subject', StoreTables::SUSPENSIONS)),
            );
        } finally {
            $shared->close();
        }

        $availability->resumeDatabase(Placement::dedicated('phil_tdb_wholesale'));
        $availability->resumeTenant(TenantId::fromString('retail'));
        self::assertSame(200, $this->storedRequest('/invoices', 'wholesale.test')->getStatusCode());
        self::assertSame(200, $this->storedRequest('/invoices', 'retail.test')->getStatusCode());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function placementRows(string $tenant): array
    {
        $shared = $this->onDatabase(TestKernel::SHARED);

        try {
            return $shared->fetchAllAssociative(\sprintf('SELECT tenant, database_name FROM %s WHERE id = ?', StoreTables::PLACEMENTS), [hash('sha256', $tenant)]);
        } finally {
            $shared->close();
        }
    }

    private function storedRequest(string $path, string $host): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        $this->context()->leave();
        $response = $kernel->handle(Request::create('http://'.$host.$path));
        $this->service('services_resetter', ResetInterface::class)->reset();

        return $response;
    }

    private function storedCommand(string $name): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        return new CommandTester(new Application($kernel)->find($name));
    }
}
