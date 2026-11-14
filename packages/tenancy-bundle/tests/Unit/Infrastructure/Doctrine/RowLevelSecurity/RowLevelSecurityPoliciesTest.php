<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\RowLevelSecurity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityConfig;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityPolicies;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\TenantTable;
use Phil\TenancyBundle\Infrastructure\Doctrine\TenantColumn;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\InheritanceRoot;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\NotScoped;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ScopedByAssociation;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ScopedByField;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ScopedSubclass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RowLevelSecurityPolicies::class)]
#[CoversClass(TenantTable::class)]
#[CoversClass(TenantColumn::class)]
final class RowLevelSecurityPoliciesTest extends TestCase
{
    public function testEachTenantScopedRootIsATableWithItsTenantColumn(): void
    {
        $tables = new RowLevelSecurityPolicies(new RowLevelSecurityConfig())->tables($this->entityManager());

        self::assertSame(
            [['"Line"', 'tenant_id'], ['billing.invoice', 'tenant_ref']],
            array_map(static fn (TenantTable $table): array => [$table->table, $table->column], $tables),
            'Sorted by name; the unscoped entity, the subclass and the mapped superclass are left out.',
        );
    }

    public function testATableIsPutUnderRowLevelSecurityAgainAndAgain(): void
    {
        self::assertSame([
            'ALTER TABLE billing.invoice ENABLE ROW LEVEL SECURITY',
            'ALTER TABLE billing.invoice FORCE ROW LEVEL SECURITY',
            'DROP POLICY IF EXISTS phil_tenant_isolation ON billing.invoice',
            "CREATE POLICY phil_tenant_isolation ON billing.invoice USING (tenant_ref::text = current_setting('phil.tenant_id', true)) WITH CHECK (tenant_ref::text = current_setting('phil.tenant_id', true))",
        ], new RowLevelSecurityPolicies(new RowLevelSecurityConfig())->enable(new TenantTable('billing.invoice', 'tenant_ref')));
    }

    public function testTheNamesOfTheConfigurationAreUsed(): void
    {
        $policies = new RowLevelSecurityPolicies(new RowLevelSecurityConfig('app.tenant', 'rows_of_tenant'));
        $table = new TenantTable('invoice', 'tenant_ref');

        self::assertSame("CREATE POLICY rows_of_tenant ON invoice USING (tenant_ref::text = current_setting('app.tenant', true)) WITH CHECK (tenant_ref::text = current_setting('app.tenant', true))", $policies->enable($table)[3]);
        self::assertSame([
            'DROP POLICY IF EXISTS rows_of_tenant ON invoice',
            'ALTER TABLE invoice NO FORCE ROW LEVEL SECURITY',
            'ALTER TABLE invoice DISABLE ROW LEVEL SECURITY',
        ], $policies->disable($table));
    }

    private function entityManager(): EntityManagerInterface
    {
        $invoice = new ClassMetadata(ScopedByField::class);
        $invoice->setPrimaryTable(['name' => 'invoice', 'schema' => 'billing']);
        $invoice->mapField(['fieldName' => 'tenantRef', 'columnName' => 'tenant_ref', 'type' => 'string']);

        $line = new ClassMetadata(ScopedByAssociation::class);
        $line->setPrimaryTable(['name' => 'Line', 'quoted' => true]);
        $line->mapManyToOne(['fieldName' => 'tenant', 'targetEntity' => NotScoped::class, 'joinColumns' => [['name' => 'tenant_id', 'referencedColumnName' => 'id']]]);

        $unscoped = new ClassMetadata(NotScoped::class);
        $unscoped->setPrimaryTable(['name' => 'tenant']);

        $subclass = new ClassMetadata(ScopedSubclass::class);
        $subclass->rootEntityName = InheritanceRoot::class;

        $superclass = new ClassMetadata(ScopedByField::class);
        $superclass->isMappedSuperclass = true;

        $factory = self::createStub(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn([$subclass, $invoice, $unscoped, $line, $superclass]);

        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($factory);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getConfiguration')->willReturn(new Configuration());

        return $entityManager;
    }
}
