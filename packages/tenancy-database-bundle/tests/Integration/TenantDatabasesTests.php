<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration;

use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\TenantDatabaseAvailability;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantNotMoved;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantSwitchInTransaction;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseRouter;
use Phil\TenancyDatabaseBundle\Infrastructure\Move\TenantMover;
use Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity\Invoice;
use Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity\Party;
use Phil\TenancyDatabaseBundle\Tests\Integration\App\MemoryPlacements;
use Phil\TenancyDatabaseBundle\Tests\Integration\App\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * What every server is tested for: one connection that follows the placement of the active tenant, the refusals that
 * keep a tenant's data in its database, and the commands that create, migrate and move the databases.
 */
trait TenantDatabasesTests
{
    public function testTheKernelLeavesNoTrustedHostToTheTestsAfterIt(): void
    {
        self::assertSame(200, $this->request('/invoices', 'wholesale.test')->getStatusCode());
        self::ensureKernelShutdown();

        self::assertSame('localhost', Request::create('http://localhost/')->getHost());
    }

    public function testEachTenantReadsItsOwnDatabaseWithinOneRequest(): void
    {
        $this->seed();

        $response = $this->request('/both', 'retail.test');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([
            '0-retail' => ['entities' => ['R-1', 'R-2'], 'native' => ['R-1', 'R-2'], 'first' => 'R-1'],
            '1-wholesale' => ['entities' => ['W-1'], 'native' => ['W-1'], 'first' => 'W-1'],
            '2-retail' => ['entities' => ['R-1', 'R-2'], 'native' => ['R-1', 'R-2'], 'first' => 'R-1'],
        ], json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testTheRowsOfEachTenantAreWrittenInItsOwnDatabase(): void
    {
        $this->seed();

        self::assertSame(['R-1', 'R-2'], $this->labelsIn(TestKernel::SHARED, 'retail'));
        self::assertSame([], $this->labelsIn(TestKernel::SHARED, 'wholesale'));
        self::assertSame(['W-1'], $this->labelsIn('phil_tdb_wholesale', 'wholesale'));
        self::assertSame([], $this->labelsIn('phil_tdb_wholesale', 'retail'));
    }

    public function testTheHostOfADedicatedTenantIsServedFromItsDatabase(): void
    {
        $this->seed();

        $response = $this->request('/invoices', 'wholesale.test');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['entities' => ['W-1'], 'native' => ['W-1'], 'first' => 'W-1'], json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testATenantOfAnotherDatabaseIsRefusedInsideATransaction(): void
    {
        $this->seed();
        $connection = $this->entityManager()->getConnection();

        $this->in('retail', function () use ($connection): void {
            $connection->beginTransaction();

            try {
                $this->context()->enter(TenantId::fromString('wholesale'));
                self::fail('The switch was not refused.');
            } catch (TenantSwitchInTransaction $tenantSwitchInTransaction) {
                self::assertSame('The tenant "wholesale" lives in the database "phil_tdb_wholesale", but a transaction is open in "shared": commit or roll it back before entering the tenant.', $tenantSwitchInTransaction->getMessage());
            }

            self::assertSame(['R-1', 'R-2'], $connection->fetchFirstColumn("SELECT label FROM invoice WHERE tenant_ref = 'retail' ORDER BY label"), 'The connection stayed in the shared database.');
            $connection->rollBack();
        });
    }

    public function testAKernelResetReturnsTheConnectionToTheSharedDatabase(): void
    {
        $this->seed();
        $this->context()->enter(TenantId::fromString('wholesale'));
        self::assertSame(['W-1'], $this->entityManager()->getConnection()->fetchFirstColumn('SELECT label FROM invoice'));

        $this->service('services_resetter', ResetInterface::class)->reset();

        self::assertTrue($this->service(TenantDatabaseRouter::class, TenantDatabaseRouter::class)->current()->isShared());
        self::assertSame(['R-1', 'R-2'], $this->entityManager()->getConnection()->fetchFirstColumn('SELECT label FROM invoice ORDER BY label'));
        self::assertSame([], $this->entityManager()->getRepository(Invoice::class)->findAll(), 'No tenant: the filter shows nothing.');
    }

    public function testTheDatabaseOfANewTenantIsCreatedOnce(): void
    {
        self::assertFalse($this->databaseExists('phil_tdb_newcomer'));

        $first = $this->command('phil:tenancy:database:create');
        self::assertSame(0, $first->execute(['tenant' => 'newcomer', '--no-migrate' => true]), $first->getDisplay());
        self::assertStringContainsString('The database phil_tdb_newcomer was created.', $first->getDisplay());
        self::assertTrue($this->databaseExists('phil_tdb_newcomer'));

        $again = $this->command('phil:tenancy:database:create');
        self::assertSame(0, $again->execute(['tenant' => 'newcomer', '--no-migrate' => true]));
        self::assertStringContainsString('The database phil_tdb_newcomer exists already.', $again->getDisplay());

        $shared = $this->command('phil:tenancy:database:create');
        self::assertSame(0, $shared->execute(['tenant' => 'retail']));
        self::assertStringContainsString('lives in the shared database', $shared->getDisplay());
    }

    public function testTheCreatedDatabaseIsMigrated(): void
    {
        $tester = $this->command('phil:tenancy:database:create');

        self::assertSame(0, $tester->execute(['tenant' => 'newcomer']), $tester->getDisplay());
        self::assertStringContainsString('The database phil_tdb_newcomer is migrated.', $tester->getDisplay());
        self::assertSame(0, $this->countIn('phil_tdb_newcomer', 'invoice', 'newcomer'));
    }

    public function testAFailedMigrationSuspendsItsTenantsOnlyAndASuccessfulOneResumesThem(): void
    {
        $this->seed();
        // `newcomer`'s database does not exist: its migration fails, the others succeed.
        $tester = $this->command('phil:tenancy:database:migrate');

        self::assertSame(1, $tester->execute(['--concurrency' => '3']));
        $display = $tester->getDisplay();
        self::assertStringContainsString('✓ shared', $display);
        self::assertStringContainsString('✓ phil_tdb_wholesale', $display);
        self::assertMatchesRegularExpression('/✗ phil_tdb_newcomer failed with exit code \d+; its tenants are suspended\./', $display);
        self::assertStringContainsString('2 of 3 databases migrated.', $display);

        $refused = $this->request('/invoices', 'newcomer.test');
        self::assertSame(503, $refused->getStatusCode());
        self::assertSame('30', $refused->headers->get('Retry-After'));
        self::assertSame(200, $this->request('/invoices', 'retail.test')->getStatusCode());
        self::assertSame(200, $this->request('/invoices', 'wholesale.test')->getStatusCode());

        $this->server()->executeStatement('CREATE DATABASE phil_tdb_newcomer');
        $again = $this->command('phil:tenancy:database:migrate');
        self::assertSame(0, $again->execute(['--only' => ['phil_tdb_newcomer']]), $again->getDisplay());
        self::assertStringContainsString('1 of 1 databases migrated.', $again->getDisplay());
        self::assertSame(200, $this->request('/invoices', 'newcomer.test')->getStatusCode());
    }

    public function testATenantMovesToADatabaseOfItsOwnAndBack(): void
    {
        $this->seed();
        $mover = $this->service(TenantMover::class, TenantMover::class);
        $retail = TenantId::fromString('retail');

        $report = $mover->move($retail, Placement::dedicated(self::MOVED));

        self::assertTrue($report->moved());
        self::assertTrue($report->sourcePurged);
        self::assertSame(['label' => 1, 'party' => 1, 'invoice' => 2, 'invoice_label' => 2], $report->rows);
        self::assertSame(['R-1', 'R-2'], $this->labelsIn(self::MOVED, 'retail'));
        self::assertSame([], $this->labelsIn(TestKernel::SHARED, 'retail'));
        self::assertSame(0, $this->countIn(TestKernel::SHARED, 'party', 'retail'));
        self::assertSame(2, $this->countIn(self::MOVED, 'invoice_label', 'retail'));
        self::assertTrue($this->placements()->placementOf($retail)->equals(Placement::dedicated(self::MOVED)));
        self::assertFalse($this->service(TenantDatabaseAvailability::class, TenantDatabaseAvailability::class)->isTenantSuspended($retail));

        $read = $this->in('retail', fn (): array => array_map(static fn (Invoice $invoice): array => [$invoice->label, $invoice->party->name, \count($invoice->labels), $invoice->paid, self::text($invoice->scan)], $this->entityManager()->getRepository(Invoice::class)->findBy([], ['label' => 'ASC'])));
        self::assertSame([['R-1', 'retail customer', 1, true, "\x00\xFFR-1"], ['R-2', 'retail customer', 1, false, "\x00\xFFR-2"]], $read);

        // A row inserted in the target after the move gets a key past the copied ones.
        $this->in('retail', function (): void {
            $entityManager = $this->entityManager();
            $party = $entityManager->getRepository(Party::class)->findOneBy([]);
            self::assertNotNull($party);
            $entityManager->persist(new Invoice('retail', 'R-3', $party));
            $entityManager->flush();
        });

        $back = $mover->move($retail, Placement::shared());

        self::assertSame(['label' => 1, 'party' => 1, 'invoice' => 3, 'invoice_label' => 2], $back->rows);
        self::assertSame(['R-1', 'R-2', 'R-3'], $this->labelsIn(TestKernel::SHARED, 'retail'));
        self::assertSame([], $this->labelsIn(self::MOVED, 'retail'));
        self::assertTrue($this->placements()->placementOf($retail)->isShared());
    }

    public function testAMoveThatCollidesIsRolledBack(): void
    {
        $this->seed();
        $wholesale = TenantId::fromString('wholesale');

        // `wholesale`'s rows have the keys of `retail`'s in the shared database.
        try {
            $this->service(TenantMover::class, TenantMover::class)->move($wholesale, Placement::shared());
            self::fail('The move was not refused.');
        } catch (TenantNotMoved $tenantNotMoved) {
            self::assertStringStartsWith('The tenant "wholesale" was not moved to "shared": ', $tenantNotMoved->getMessage());
        }

        self::assertSame(['W-1'], $this->labelsIn('phil_tdb_wholesale', 'wholesale'));
        self::assertSame([], $this->labelsIn(TestKernel::SHARED, 'wholesale'));
        self::assertSame(0, $this->countIn(TestKernel::SHARED, 'party', 'wholesale'));
        self::assertTrue($this->placements()->placementOf($wholesale)->equals(Placement::dedicated('phil_tdb_wholesale')));
        self::assertFalse($this->service(TenantDatabaseAvailability::class, TenantDatabaseAvailability::class)->isTenantSuspended($wholesale));
    }

    public function testAMoveWhoseStoreFailsIsRolledBack(): void
    {
        $this->seed();
        $retail = TenantId::fromString('retail');
        $this->placements()->failOnNextPlace();

        $tester = $this->command('phil:tenancy:database:move');

        self::assertSame(1, $tester->execute(['tenant' => 'retail', 'target' => self::MOVED]));
        self::assertStringContainsString('The tenant "retail" was not moved to "phil_tdb_moved": The placement store is down.', $tester->getDisplay());
        self::assertSame(['R-1', 'R-2'], $this->labelsIn(TestKernel::SHARED, 'retail'));
        self::assertSame([], $this->labelsIn(self::MOVED, 'retail'));
        self::assertSame(0, $this->countIn(self::MOVED, 'party', 'retail'));
        self::assertTrue($this->placements()->placementOf($retail)->isShared());
    }

    public function testTheMoveCommandReportsTheRowsAndCanKeepTheSource(): void
    {
        $this->seed();
        $tester = $this->command('phil:tenancy:database:move');

        self::assertSame(0, $tester->execute(['tenant' => 'retail', 'target' => self::MOVED, '--keep-source' => true]), $tester->getDisplay());
        $display = $tester->getDisplay();
        self::assertStringContainsString('invoice: 2 rows', $display);
        self::assertStringContainsString('The tenant "retail" moved from shared to phil_tdb_moved; its rows are still in shared.', $display);
        self::assertSame(['R-1', 'R-2'], $this->labelsIn(TestKernel::SHARED, 'retail'));
        self::assertSame(['R-1', 'R-2'], $this->labelsIn(self::MOVED, 'retail'));

        $again = $this->command('phil:tenancy:database:move');
        self::assertSame(0, $again->execute(['tenant' => 'retail', 'target' => self::MOVED]));
        self::assertStringContainsString('The tenant "retail" lives in phil_tdb_moved already.', $again->getDisplay());
    }

    private function request(string $path, string $host): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        $this->context()->leave();
        $response = $kernel->handle(Request::create('http://'.$host.$path));
        $this->service('services_resetter', ResetInterface::class)->reset();

        return $response;
    }

    private function command(string $name): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        return new CommandTester(new Application($kernel)->find($name));
    }

    private function placements(): MemoryPlacements
    {
        return $this->service(MemoryPlacements::class, MemoryPlacements::class);
    }

    private static function text(mixed $value): mixed
    {
        return \is_resource($value) ? stream_get_contents($value) : $value;
    }
}
