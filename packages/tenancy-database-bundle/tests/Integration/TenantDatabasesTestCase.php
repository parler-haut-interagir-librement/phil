<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\TenancyBundle\Application\Contract\TenantPlacement;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\PlacementStore;
use Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity\Currency;
use Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity\Invoice;
use Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity\Label;
use Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity\Party;
use Phil\TenancyDatabaseBundle\Tests\Integration\App\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The bundle on a real server, with three databases: `phil_tdb_shared`, where `retail` lives beside the rows the
 * Doctrine filter keeps apart, `phil_tdb_wholesale` for `wholesale`, and `phil_tdb_moved`, the target of a move.
 * `phil_tdb_newcomer`, the database of `newcomer`, is left for the tests to create.
 *
 * Each database is dropped and created again for each test. The account of the server's URL must be allowed to
 * create databases: the test is skipped otherwise.
 */
abstract class TenantDatabasesTestCase extends KernelTestCase
{
    public const string MOVED = 'phil_tdb_moved';

    private const array DATABASES = [TestKernel::SHARED, 'phil_tdb_wholesale', 'phil_tdb_newcomer', self::MOVED];

    private ?Connection $server = null;

    /**
     * The variables that may hold the server's URL, the first one set winning.
     *
     * @return list<string>
     */
    abstract protected static function variables(): array;

    /**
     * Whether the placements are read from the table of the shared database.
     */
    protected static function storedPlacements(): bool
    {
        return false;
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, static::storedPlacements());
    }

    #[Override]
    protected function setUp(): void
    {
        $url = null;

        foreach (static::variables() as $variable) {
            $value = getenv($variable);

            if (\is_string($value) && '' !== $value) {
                $url = $value;

                break;
            }
        }

        if (null === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', implode(' or ', static::variables())));
        }

        // Read by the kernel, and by the processes of the migrations, which Symfony Process gives the variables of $_ENV.
        $_SERVER[TestKernel::URL_VARIABLE] = $url;
        $_ENV[TestKernel::URL_VARIABLE] = $url;
        $server = TestKernel::parameters(null);

        if ('pdo_pgsql' === ($server['driver'] ?? null)) {
            $server['dbname'] = 'postgres';
        }

        $this->server = DriverManager::getConnection($server);

        try {
            foreach (self::DATABASES as $database) {
                $this->drop($database);
            }

            foreach ([TestKernel::SHARED, 'phil_tdb_wholesale', self::MOVED] as $database) {
                $this->server->executeStatement('CREATE DATABASE '.$database);
            }
        } catch (DbalException $dbalException) {
            // Nothing was created: tearDown() has nothing to drop, and would fail on the same refusal.
            $this->server->close();
            $this->server = null;
            self::markTestSkipped('The account of the server cannot create databases: '.$dbalException->getMessage());
        }

        self::bootKernel();

        // The tables of the stores, created with the schema of the shared database, start empty with it.
        foreach ([null, 'wholesale'] as $tenant) {
            if (null !== $tenant && static::storedPlacements()) {
                $store = $this->service(TenantPlacement::class, PlacementStore::class);

                foreach (TestKernel::DEDICATED as $dedicated => $database) {
                    $store->place(TenantId::fromString($dedicated), Placement::dedicated($database));
                }
            }

            $this->in($tenant, function (): void {
                $entityManager = $this->entityManager();
                new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
                $entityManager->persist(new Currency('CAD'));
                $entityManager->flush();
            });
        }

        $this->routeMovedDatabaseSchema();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->server instanceof Connection) {
            foreach (self::DATABASES as $database) {
                $this->drop($database);
            }

            $this->server->close();
        }
    }

    /**
     * Runs work in a tenant, or in none.
     */
    protected function in(?string $tenant, callable $work): mixed
    {
        $context = $this->context();

        if (null === $tenant) {
            $context->leave();

            return $work();
        }

        return $context->within(TenantId::fromString($tenant), $work);
    }

    /**
     * Two customers' invoices of `retail` and one of `wholesale`, labelled; `retail` also has a customer of its own
     * table order, and `wholesale`'s first invoice has the same key as `retail`'s.
     */
    protected function seed(): void
    {
        foreach (['retail' => ['R-1', 'R-2'], 'wholesale' => ['W-1']] as $tenant => $labels) {
            $this->in($tenant, function () use ($tenant, $labels): void {
                $entityManager = $this->entityManager();
                $party = new Party($tenant, $tenant.' customer');
                $label = new Label($tenant, 'urgent');
                $entityManager->persist($party);
                $entityManager->persist($label);

                foreach ($labels as $i => $text) {
                    $invoice = new Invoice($tenant, $text, $party, "\x00\xFF".$text, 0 === $i);
                    $invoice->labels->add($label);
                    $entityManager->persist($invoice);
                }

                $entityManager->flush();
                $entityManager->clear();
            });
        }
    }

    /**
     * The labels of the invoices of a tenant in one database, read on the server with native SQL.
     *
     * @return list<string>
     */
    protected function labelsIn(string $database, string $tenant): array
    {
        $connection = $this->onDatabase($database);

        try {
            /** @var list<string> */
            return $connection->fetchFirstColumn('SELECT label FROM invoice WHERE tenant_ref = ? ORDER BY label', [$tenant]);
        } finally {
            $connection->close();
        }
    }

    protected function countIn(string $database, string $table, string $tenant = ''): int
    {
        $connection = $this->onDatabase($database);

        try {
            $count = $connection->fetchOne(match ($table) {
                'invoice_label' => 'SELECT COUNT(*) FROM invoice_label WHERE invoice_id IN (SELECT id FROM invoice WHERE tenant_ref = ?)',
                default => \sprintf('SELECT COUNT(*) FROM %s WHERE tenant_ref = ?', $table),
            }, [$tenant]);

            return is_numeric($count) ? (int) $count : -1;
        } finally {
            $connection->close();
        }
    }

    protected function databaseExists(string $database): bool
    {
        $server = $this->server();
        $sql = $server->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? 'SELECT COUNT(*) FROM pg_database WHERE datname = ?'
            : 'SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?';

        $count = $server->fetchOne($sql, [$database]);

        return is_numeric($count) && 1 === (int) $count;
    }

    protected function context(): TenantContext
    {
        return $this->service(TenantContext::class, TenantContext::class);
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    private function routeMovedDatabaseSchema(): void
    {
        $connection = $this->onDatabase(self::MOVED);
        $entityManager = $this->entityManager();

        try {
            foreach (new SchemaTool($entityManager)->getCreateSchemaSql($entityManager->getMetadataFactory()->getAllMetadata()) as $statement) {
                $connection->executeStatement($statement);
            }

            $connection->executeStatement("INSERT INTO currency (code) VALUES ('CAD')");
        } finally {
            $connection->close();
        }
    }

    protected function onDatabase(string $database): Connection
    {
        return DriverManager::getConnection(TestKernel::parameters($database));
    }

    protected function server(): Connection
    {
        $this->assertInstanceOf(Connection::class, $this->server);

        return $this->server;
    }

    private function drop(string $database): void
    {
        $server = $this->server();
        $server->executeStatement($server->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? \sprintf('DROP DATABASE IF EXISTS %s WITH (FORCE)', $database)
            : \sprintf('DROP DATABASE IF EXISTS %s', $database));
    }
}
