<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;
use Phil\TenancyBundle\Infrastructure\Exception\MisconfiguredTenantScope;
use ReflectionAttribute;
use ReflectionClass;

/**
 * The column that holds the tenant of an entity, as the filter and the row-level security policies both read it.
 */
final class TenantColumn
{
    /**
     * The tenant column of an entity, or `null` when it is not tenant-scoped.
     *
     * @param ClassMetadata<object> $metadata
     *
     * @throws MisconfiguredTenantScope when the scope names no field or association, or sits below the root
     */
    public static function of(ClassMetadata $metadata): ?string
    {
        $field = self::field($metadata);

        return null === $field ? null : self::column($metadata, $field);
    }

    /**
     * The field or single-column association that holds the tenant of an entity, or `null` when it is not
     * tenant-scoped.
     *
     * @param ClassMetadata<object> $metadata
     *
     * @throws MisconfiguredTenantScope when the scope names no field or association, or sits below the root
     */
    public static function field(ClassMetadata $metadata): ?string
    {
        $attributes = new ReflectionClass($metadata->getName())->getAttributes(TenantScoped::class, ReflectionAttribute::IS_INSTANCEOF);

        if ([] === $attributes) {
            self::refuseScopedSubclasses($metadata);

            return null;
        }

        $field = $attributes[0]->newInstance()->field;
        self::column($metadata, $field);

        return $field;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private static function refuseScopedSubclasses(ClassMetadata $metadata): void
    {
        foreach ($metadata->subClasses as $subclass) {
            if ([] !== new ReflectionClass($subclass)->getAttributes(TenantScoped::class, ReflectionAttribute::IS_INSTANCEOF)) {
                throw MisconfiguredTenantScope::onSubclass($metadata->getName(), $subclass);
            }
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private static function column(ClassMetadata $metadata, string $field): string
    {
        if ($metadata->hasField($field)) {
            return $metadata->getColumnName($field);
        }

        if ($metadata->isAssociationWithSingleJoinColumn($field)) {
            return $metadata->getSingleAssociationJoinColumnName($field);
        }

        throw MisconfiguredTenantScope::forField($metadata->getName(), $field);
    }
}
