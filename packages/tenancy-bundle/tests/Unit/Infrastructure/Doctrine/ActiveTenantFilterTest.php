<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\FilterCollection;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter;
use Phil\TenancyBundle\Infrastructure\Doctrine\TenantColumn;
use Phil\TenancyBundle\Infrastructure\Exception\MisconfiguredTenantScope;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\InheritanceRoot;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\NotScoped;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ScopedByAssociation;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ScopedByField;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ScopedSubclass;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\UnscopedSubclass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The filter is exercised on hand-built metadata and a stubbed connection: no database, no entity manager.
 */
#[CoversClass(ActiveTenantFilter::class)]
#[CoversClass(MisconfiguredTenantScope::class)]
#[CoversClass(TenantColumn::class)]
final class ActiveTenantFilterTest extends TestCase
{
    public function testAnEntityWithoutTheAttributeIsLeftAlone(): void
    {
        $filter = $this->filter();
        $filter->activate(TenantId::fromString('retail'));

        self::assertSame('', $filter->addFilterConstraint(new ClassMetadata(NotScoped::class), 't0'));
    }

    public function testAScopedEntityIsRestrictedToTheActiveTenantOnItsColumn(): void
    {
        $filter = $this->filter();
        $filter->activate(TenantId::fromString('retail'));

        self::assertSame("t0.tenant_ref = 'retail'", $filter->addFilterConstraint($this->byField(), 't0'));
    }

    public function testAnAssociationIsRestrictedOnItsJoinColumn(): void
    {
        $filter = $this->filter();
        $filter->activate(TenantId::fromString('wholesale'));

        self::assertSame("p1_.tenant_id = 'wholesale'", $filter->addFilterConstraint($this->byAssociation(), 'p1_'));
    }

    /**
     * The value goes through the connection's quoting, never straight into the SQL.
     */
    public function testTheTenantIsQuotedByTheConnection(): void
    {
        $filter = $this->filter();
        $filter->activate(TenantId::fromString("o'brien"));

        self::assertSame("t0.tenant_ref = 'o''brien'", $filter->addFilterConstraint($this->byField(), 't0'));
    }

    /**
     * A request that forgot to set the active tenant sees no tenant's rows, rather than every tenant's.
     */
    public function testWithoutAnActiveTenantAScopedEntityMatchesNothing(): void
    {
        self::assertSame('1 = 0', $this->filter()->addFilterConstraint($this->byField(), 't0'));
    }

    public function testWithoutAnActiveTenantAnUnscopedEntityIsStillLeftAlone(): void
    {
        self::assertSame('', $this->filter()->addFilterConstraint(new ClassMetadata(NotScoped::class), 't0'));
    }

    /**
     * A scope the filter cannot apply is a mapping error, raised even while the filter is closed, so it surfaces
     * on the first query rather than on the first request with an active tenant.
     */
    public function testAFieldThatIsNeitherMappedNorAnAssociationIsAMappingError(): void
    {
        $this->expectException(MisconfiguredTenantScope::class);
        $this->expectExceptionMessageIsOrContains('names the field "tenantRef"');

        $this->filter()->addFilterConstraint(new ClassMetadata(ScopedByField::class), 't0');
    }

    /**
     * Doctrine hands the filter the root of a hierarchy: a scope declared only on a subclass would be skipped,
     * and the subclass's rows shown to every tenant.
     */
    public function testAScopeOnASubclassOfAnUnscopedRootIsAMappingError(): void
    {
        $metadata = new ClassMetadata(InheritanceRoot::class);
        $metadata->setSubclasses([ScopedSubclass::class]);

        $this->expectException(MisconfiguredTenantScope::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The entity "%s" is tenant-scoped but its inheritance root "%s" is not', ScopedSubclass::class, InheritanceRoot::class));

        $this->filter()->addFilterConstraint($metadata, 't0');
    }

    public function testAnUnscopedHierarchyIsLeftAlone(): void
    {
        $metadata = new ClassMetadata(InheritanceRoot::class);
        $metadata->setSubclasses([UnscopedSubclass::class]);

        self::assertSame('', $this->filter()->addFilterConstraint($metadata, 't0'));
    }

    /**
     * The scope of a class is read once: a later query on the same class reuses the column found first, scoped or
     * not, and the tenant set since still applies.
     */
    public function testTheScopeOfAClassIsReadOnce(): void
    {
        $filter = $this->filter();
        $scoped = $this->byField();
        $unscoped = new ClassMetadata(InheritanceRoot::class);

        self::assertSame('1 = 0', $filter->addFilterConstraint($scoped, 't0'));
        self::assertSame('', $filter->addFilterConstraint($unscoped, 't1'));

        $scoped->fieldMappings['tenantRef']->columnName = 'renamed';
        $unscoped->setSubclasses([ScopedSubclass::class]);
        $filter->activate(TenantId::fromString('retail'));

        self::assertSame("t2.tenant_ref = 'retail'", $filter->addFilterConstraint($scoped, 't2'));
        self::assertSame('', $filter->addFilterConstraint($unscoped, 't3'));
    }

    private function filter(): ActiveTenantFilter
    {
        $connection = self::createStub(Connection::class);
        $connection->method('quote')->willReturnCallback(static fn (string $value): string => "'".str_replace("'", "''", $value)."'");

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getFilters')->willReturn(new FilterCollection($entityManager));

        return new ActiveTenantFilter($entityManager);
    }

    /**
     * @return ClassMetadata<ScopedByField>
     */
    private function byField(): ClassMetadata
    {
        $metadata = new ClassMetadata(ScopedByField::class);
        $metadata->mapField(['fieldName' => 'tenantRef', 'columnName' => 'tenant_ref', 'type' => 'string']);

        return $metadata;
    }

    /**
     * @return ClassMetadata<ScopedByAssociation>
     */
    private function byAssociation(): ClassMetadata
    {
        $metadata = new ClassMetadata(ScopedByAssociation::class);
        $metadata->mapManyToOne([
            'fieldName' => 'tenant',
            'targetEntity' => NotScoped::class,
            'joinColumns' => [['name' => 'tenant_id', 'referencedColumnName' => 'id']],
        ]);

        return $metadata;
    }
}
