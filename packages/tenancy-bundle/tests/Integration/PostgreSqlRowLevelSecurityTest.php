<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Console\RowLevelSecurityCheckCommand;
use Phil\TenancyBundle\Infrastructure\Console\RowLevelSecurityPoliciesCommand;
use Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityPolicies;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\TenantSession;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\TenantSessionDriver;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\TenantSessionMiddleware;
use Phil\TenancyBundle\Tests\Functional\App\Entity\Currency;
use Phil\TenancyBundle\Tests\Functional\App\Entity\Invoice;
use Phil\TenancyBundle\Tests\Functional\App\TestKernel;
use Phil\TenancyBundle\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The second barrier on a real PostgreSQL: the policies the bundle generates, applied to the table of invoices, and
 * the session variable the middleware sets on each connection. The rows are read through native SQL and with the
 * Doctrine filter disabled, which the filter alone would let through.
 *
 * The server's own user is a superuser, whom no policy holds: each test switches the connection to a plain role.
 *
 *     PHIL_TENANCY_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' vendor/bin/phpunit tests/Integration
 *
 * `PHIL_TEST_POSTGRESQL_URL`, which `phil-dev services env` prints, is read when the first is not set.
 */
#[CoversClass(TenantSession::class)]
#[CoversClass(TenantSessionMiddleware::class)]
#[CoversClass(TenantSessionDriver::class)]
#[CoversClass(RowLevelSecurityPolicies::class)]
#[CoversClass(RowLevelSecurityPoliciesCommand::class)]
#[CoversClass(RowLevelSecurityCheckCommand::class)]
#[Group('postgresql')]
final class PostgreSqlRowLevelSecurityTest extends KernelTestCase
{
    private const string ROLE = 'phil_tenancy_rls_probe';

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, ['row_level_security' => ['enabled' => true]], self::url());
    }

    #[Override]
    protected function setUp(): void
    {
        if (null === self::url()) {
            self::markTestSkipped('Set PHIL_TENANCY_TEST_POSTGRESQL_URL or PHIL_TEST_POSTGRESQL_URL to run this test against a real PostgreSQL.');
        }

        FunctionalTestCase::skipWithoutEventDispatcher();
        self::bootKernel();
        $entityManager = $this->entityManager();
        $connection = $this->connection();
        $connection->executeStatement('RESET ROLE');

        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        foreach ([new Invoice('retail', 'R-1'), new Invoice('retail', 'R-2'), new Invoice('wholesale', 'W-1'), new Currency('CAD')] as $entity) {
            $entityManager->persist($entity);
        }

        $entityManager->flush();
        $entityManager->clear();

        foreach ($this->command(RowLevelSecurityPoliciesCommand::class) as $statement) {
            $connection->executeStatement($statement);
        }

        $connection->executeStatement(\sprintf('DO $$ BEGIN CREATE ROLE %s NOLOGIN; EXCEPTION WHEN duplicate_object THEN NULL; END $$', self::ROLE));
        $connection->executeStatement(\sprintf('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO %s', self::ROLE));
        $connection->executeStatement(\sprintf('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO %s', self::ROLE));
        $connection->executeStatement(\sprintf('SET ROLE %s', self::ROLE));
    }

    protected function tearDown(): void
    {
        if (null !== self::url() && self::$kernel instanceof KernelInterface) {
            $this->connection()->executeStatement('RESET ROLE');
        }

        parent::tearDown();
    }

    public function testWithoutATenantNoRowIsVisibleEvenWithTheFilterDisabled(): void
    {
        $this->entityManager()->getFilters()->disable(ActiveTenantFilter::NAME);

        self::assertSame([], $this->labels());
        self::assertSame(['CAD'], $this->connection()->fetchFirstColumn('SELECT code FROM currency'), 'A table that is not tenant-scoped is left alone.');
    }

    public function testTheActiveTenantSeesOnlyItsRowsThroughNativeSql(): void
    {
        $context = $this->context();

        $context->enter(TenantId::fromString('retail'));
        self::assertSame(['R-1', 'R-2'], $this->labels());

        $context->enter(TenantId::fromString('wholesale'));
        self::assertSame(['W-1'], $this->labels());

        $context->leave();
        self::assertSame([], $this->labels());
    }

    public function testATenantReferenceShapedLikeSqlIsOnlyAnUnknownTenant(): void
    {
        $this->context()->enter(TenantId::fromString("retail' OR '1' = '1"));

        self::assertSame([], $this->labels());
    }

    public function testAConnectionOpenedAgainTakesTheActiveTenant(): void
    {
        $this->context()->enter(TenantId::fromString('retail'));
        $connection = $this->connection();
        $connection->close();

        // The new session is the server's user again: the role is set anew, the tenant came with the connection.
        $connection->executeStatement(\sprintf('SET ROLE %s', self::ROLE));

        self::assertSame('retail', $connection->fetchOne("SELECT current_setting('phil.tenant_id', true)"));
        self::assertSame(['R-1', 'R-2'], $this->labels());
    }

    public function testARowOfAnotherTenantCannotBeWritten(): void
    {
        $this->context()->enter(TenantId::fromString('retail'));
        $connection = $this->connection();

        self::assertSame(1, $connection->executeStatement("INSERT INTO invoice (tenant_ref, label) VALUES ('retail', 'R-3')"));
        self::assertSame(0, $connection->executeStatement("UPDATE invoice SET label = 'stolen' WHERE tenant_ref = 'wholesale'"));

        $this->expectException(DbalException::class);
        $this->expectExceptionMessageIsOrContains('row-level security');

        $connection->executeStatement("INSERT INTO invoice (tenant_ref, label) VALUES ('wholesale', 'W-2')");
    }

    public function testTheCheckPassesWhenEveryTableIsHeld(): void
    {
        $tester = $this->tester(RowLevelSecurityCheckCommand::class);

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('1 tenant-scoped tables are held by the policy phil_tenant_isolation.', $tester->getDisplay());
    }

    public function testTheCheckFailsForARoleThatBypassesThePolicies(): void
    {
        $this->connection()->executeStatement('RESET ROLE');
        $tester = $this->tester(RowLevelSecurityCheckCommand::class);

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('bypasses row-level security', $tester->getDisplay());
    }

    public function testTheCheckFailsForATableLeftOutOfItsPolicy(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('RESET ROLE');
        $connection->executeStatement('DROP POLICY phil_tenant_isolation ON invoice');
        $connection->executeStatement('ALTER TABLE invoice NO FORCE ROW LEVEL SECURITY');
        $connection->executeStatement('ALTER TABLE invoice DISABLE ROW LEVEL SECURITY');
        $connection->executeStatement(\sprintf('SET ROLE %s', self::ROLE));

        $tester = $this->tester(RowLevelSecurityCheckCommand::class);

        self::assertSame(1, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('The table invoice does not have row-level security enabled.', $display);
        self::assertStringContainsString('The table invoice does not force row-level security on its owner.', $display);
        self::assertStringContainsString('The table invoice has no policy phil_tenant_isolation that reads phil.tenant_id for reading and writing.', $display);
    }

    public function testTheCheckFailsForAMissingTable(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('RESET ROLE');
        $connection->executeStatement('DROP TABLE invoice');

        $tester = $this->tester(RowLevelSecurityCheckCommand::class);

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('The table invoice does not exist.', $tester->getDisplay());
    }

    /**
     * @return list<string>
     */
    private function labels(): array
    {
        /** @var list<string> */
        return $this->connection()->fetchFirstColumn('SELECT label FROM invoice ORDER BY label');
    }

    /**
     * The statements a command prints, one per line.
     *
     * @param class-string $command
     *
     * @return list<string>
     */
    private function command(string $command): array
    {
        $tester = $this->tester($command);
        $tester->execute([]);

        return array_map(static fn (string $line): string => rtrim($line, ';'), array_values(array_filter(explode("\n", $tester->getDisplay()), static fn (string $line): bool => '' !== $line)));
    }

    /**
     * @param class-string $command
     */
    private function tester(string $command): CommandTester
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);
        $name = match ($command) {
            RowLevelSecurityPoliciesCommand::class => 'phil:tenancy:rls:policies',
            default => 'phil:tenancy:rls:check',
        };

        return new CommandTester(new Application($kernel)->find($name));
    }

    private function context(): TenantContext
    {
        $context = self::getContainer()->get(TenantContext::class);
        self::assertInstanceOf(TenantContext::class, $context);

        return $context;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }

    private static function url(): ?string
    {
        foreach (['PHIL_TENANCY_TEST_POSTGRESQL_URL', 'PHIL_TEST_POSTGRESQL_URL'] as $variable) {
            $url = getenv($variable);

            if (\is_string($url) && '' !== $url) {
                return $url;
            }
        }

        return null;
    }
}
