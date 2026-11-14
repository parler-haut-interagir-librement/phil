<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Phil\TenancyBundle\Infrastructure\Console\RowLevelSecurityCheckCommand;
use Phil\TenancyBundle\Infrastructure\Console\RowLevelSecurityPoliciesCommand;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityConfig;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityPolicies;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\NotScoped;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ScopedByAssociation;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ScopedByField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The commands without a database: the statements they print, and the refusal of a connection that is not
 * PostgreSQL. Their checks on a real server are in the PostgreSQL integration test.
 */
#[CoversClass(RowLevelSecurityPoliciesCommand::class)]
#[CoversClass(RowLevelSecurityCheckCommand::class)]
final class RowLevelSecurityCommandsTest extends TestCase
{
    public function testThePoliciesArePrintedOneStatementPerLine(): void
    {
        $tester = new CommandTester(new RowLevelSecurityPoliciesCommand($this->entityManager(true), new RowLevelSecurityPolicies(new RowLevelSecurityConfig())));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(<<<'SQL'
            ALTER TABLE invoice ENABLE ROW LEVEL SECURITY;
            ALTER TABLE invoice FORCE ROW LEVEL SECURITY;
            DROP POLICY IF EXISTS phil_tenant_isolation ON invoice;
            CREATE POLICY phil_tenant_isolation ON invoice USING (tenant_ref::text = current_setting('phil.tenant_id', true)) WITH CHECK (tenant_ref::text = current_setting('phil.tenant_id', true));

            SQL, $tester->getDisplay());
    }

    public function testTheirRemovalIsPrintedForTheDownOfAMigration(): void
    {
        $tester = new CommandTester(new RowLevelSecurityPoliciesCommand($this->entityManager(true), new RowLevelSecurityPolicies(new RowLevelSecurityConfig())));

        $tester->execute(['--disable' => true]);

        self::assertSame("DROP POLICY IF EXISTS phil_tenant_isolation ON invoice;\nALTER TABLE invoice NO FORCE ROW LEVEL SECURITY;\nALTER TABLE invoice DISABLE ROW LEVEL SECURITY;\n", $tester->getDisplay());
    }

    public function testWithoutATenantScopedEntityNothingIsPrinted(): void
    {
        $tester = new CommandTester(new RowLevelSecurityPoliciesCommand($this->entityManager(false), new RowLevelSecurityPolicies(new RowLevelSecurityConfig())));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("-- No entity is tenant-scoped.\n", $tester->getDisplay());
    }

    public function testTheCheckRefusesAConnectionThatIsNotPostgreSql(): void
    {
        $config = new RowLevelSecurityConfig();
        $tester = new CommandTester(new RowLevelSecurityCheckCommand($this->entityManager(true, new SQLitePlatform()), new RowLevelSecurityPolicies($config), $config));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Row-level security needs PostgreSQL; this connection is not.', $tester->getDisplay());
    }

    public function testTheCheckPassesWhenEveryTableIsHeld(): void
    {
        $tester = $this->check(['name' => 'app', 'rolsuper' => false, 'rolbypassrls' => false], ['invoice' => $this->held(), 'line' => $this->held()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame("✓ 2 tenant-scoped tables are held by the policy phil_tenant_isolation.\n", $tester->getDisplay());
    }

    public function testARoleTheCatalogueDoesNotListIsNoProblem(): void
    {
        self::assertSame(Command::SUCCESS, $this->check(false, ['invoice' => $this->held(), 'line' => $this->held()])->getStatusCode());
    }

    #[TestWith([true, false])]
    #[TestWith([false, true])]
    public function testARoleThatBypassesTheSecurityFailsTheCheck(bool $superuser, bool $bypass): void
    {
        $tester = $this->check(['name' => 'owner', 'rolsuper' => $superuser, 'rolbypassrls' => $bypass], ['invoice' => $this->held(), 'line' => $this->held()]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame("✗ The role owner bypasses row-level security: connect with a role that is neither a superuser nor BYPASSRLS.\n", $tester->getDisplay());
    }

    public function testEveryProblemOfEveryTableIsPrinted(): void
    {
        $tester = $this->check(['name' => 'app', 'rolsuper' => false, 'rolbypassrls' => false], ['line' => ['relrowsecurity' => false, 'relforcerowsecurity' => false, 'qual' => null, 'with_check' => null]]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame(<<<'TXT'
            ✗ The table invoice does not exist.
            ✗ The table line does not have row-level security enabled.
            ✗ The table line does not force row-level security on its owner.
            ✗ The table line has no policy phil_tenant_isolation that reads phil.tenant_id for reading and writing.

            TXT, $tester->getDisplay());
    }

    #[TestWith(['qual'])]
    #[TestWith(['with_check'])]
    public function testThePolicyReadsTheSessionVariableForReadingAndWriting(string $side): void
    {
        $tester = $this->check(['name' => 'app', 'rolsuper' => false, 'rolbypassrls' => false], ['invoice' => $this->held(), 'line' => [$side => 'true'] + $this->held()]);

        self::assertSame("✗ The table line has no policy phil_tenant_isolation that reads phil.tenant_id for reading and writing.\n", $tester->getDisplay());
    }

    /**
     * @return array{relrowsecurity: bool, relforcerowsecurity: bool, qual: string, with_check: string}
     */
    private function held(): array
    {
        $check = "((tenant_ref)::text = current_setting('phil.tenant_id'::text, true))";

        return ['relrowsecurity' => true, 'relforcerowsecurity' => true, 'qual' => $check, 'with_check' => $check];
    }

    /**
     * Runs the check on a PostgreSQL connection whose catalogue answers `$role` and, for each table, its state, or
     * nothing when the table is missing.
     *
     * @param array<string, mixed>|false          $role
     * @param array<string, array<string, mixed>> $states
     */
    private function check(array|false $role, array $states): CommandTester
    {
        $invoice = new ClassMetadata(ScopedByField::class);
        $invoice->setPrimaryTable(['name' => 'invoice']);
        $invoice->mapField(['fieldName' => 'tenantRef', 'columnName' => 'tenant_ref', 'type' => 'string']);

        $line = new ClassMetadata(ScopedByAssociation::class);
        $line->setPrimaryTable(['name' => 'line']);
        $line->mapManyToOne(['fieldName' => 'tenant', 'targetEntity' => NotScoped::class, 'joinColumns' => [['name' => 'tenant_id', 'referencedColumnName' => 'id']]]);

        $factory = self::createStub(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn([$line, $invoice]);

        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection->method('fetchAssociative')->willReturnCallback(static function (string $sql, array $params = []) use ($role, $states): array|false {
            if (str_contains($sql, 'pg_roles')) {
                return $role;
            }

            self::assertSame('phil_tenant_isolation', $params[0] ?? null, 'The policy is looked up by its name.');
            self::assertIsString($params[1] ?? null);

            return $states[$params[1]] ?? false;
        });

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($factory);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getConfiguration')->willReturn(new Configuration());

        $config = new RowLevelSecurityConfig();
        $tester = new CommandTester(new RowLevelSecurityCheckCommand($entityManager, new RowLevelSecurityPolicies($config), $config));
        $tester->execute([]);

        return $tester;
    }

    private function entityManager(bool $scoped, ?AbstractPlatform $platform = null): EntityManagerInterface
    {
        $invoice = new ClassMetadata(ScopedByField::class);
        $invoice->setPrimaryTable(['name' => 'invoice']);
        $invoice->mapField(['fieldName' => 'tenantRef', 'columnName' => 'tenant_ref', 'type' => 'string']);

        $other = new ClassMetadata(NotScoped::class);
        $other->setPrimaryTable(['name' => 'currency']);

        $factory = self::createStub(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn($scoped ? [$invoice, $other] : [$other]);

        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform ?? new PostgreSQLPlatform());

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($factory);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getConfiguration')->willReturn(new Configuration());

        return $entityManager;
    }
}
