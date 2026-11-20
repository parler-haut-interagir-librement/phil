<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Stringable;

/**
 * The tenant an entity belongs to, read from the field its `#[TenantScoped]` attribute names: the reference held in a
 * mapped field, or the identifier of the tenant entity a single-column association points to.
 */
final class EntityTenant
{
    /**
     * The entity's tenant reference, or `null` when its field holds none.
     *
     * @param ClassMetadata<object> $metadata
     */
    public static function of(EntityManagerInterface $manager, ClassMetadata $metadata, string $field, object $entity): ?string
    {
        $value = $metadata->getFieldValue($entity, $field);

        if (\is_object($value) && $metadata->hasAssociation($field)) {
            $value = $manager->getUnitOfWork()->getSingleIdentifierValue($value);
        }

        return match (true) {
            \is_string($value) => $value,
            \is_int($value), $value instanceof Stringable => (string) $value,
            default => null,
        };
    }
}
