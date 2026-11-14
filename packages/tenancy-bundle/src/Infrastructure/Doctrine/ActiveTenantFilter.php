<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Override;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Exception\MisconfiguredTenantScope;

/**
 * Restricts every {@see TenantScoped} entity to the active tenant.
 *
 * **Fail closed.** Without the {@see self::PARAMETER} parameter, a tenant-scoped entity matches nothing: a
 * request that forgot to set the active tenant sees no tenant's rows, rather than every tenant's. Work that
 * legitimately spans tenants (a console command, a report) disables the filter explicitly, where a reviewer
 * can see it.
 *
 * Entities without the attribute are left alone, unless a subclass carries it: Doctrine hands the filter the
 * root of an inheritance hierarchy, so a scope declared below the root would never be read and the hierarchy's
 * rows would be visible to every tenant. That is a mapping error ({@see MisconfiguredTenantScope}).
 *
 * The attribute is read once per entity class, and its column kept: a filter belongs to one entity manager, whose
 * mapping does not change while it lives, so the constraint of a class costs a lookup from the second query on.
 */
final class ActiveTenantFilter extends SQLFilter
{
    public const string NAME = 'phil_tenancy';

    public const string PARAMETER = 'tenant';

    /**
     * Matches no row in any SQL dialect.
     */
    private const string NOTHING = '1 = 0';

    /**
     * The tenant column of each entity class read so far, `null` for a class that is not tenant-scoped.
     *
     * @var array<string, string|null>
     */
    private array $columns = [];

    /**
     * Sets the active tenant on the filter. Call it once the tenant is resolved, before the first tenant-scoped
     * query of the request.
     */
    public function activate(TenantId $tenant): void
    {
        $this->setParameter(self::PARAMETER, $tenant->value);
    }

    /**
     * @param ClassMetadata<object> $targetEntity
     */
    #[Override]
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        $class = $targetEntity->getName();

        if (!\array_key_exists($class, $this->columns)) {
            $this->columns[$class] = TenantColumn::of($targetEntity);
        }

        $column = $this->columns[$class];

        if (null === $column) {
            return '';
        }

        if (!$this->hasParameter(self::PARAMETER)) {
            return self::NOTHING;
        }

        return \sprintf('%s.%s = %s', $targetTableAlias, $column, $this->getParameter(self::PARAMETER));
    }
}
