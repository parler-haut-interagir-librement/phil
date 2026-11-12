<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Phil\DoctrineBehaviours\Contract\SoftDeletable;

/**
 * Hides the deleted rows of `SoftDeletable` entities from every query while it is enabled; disable it to read them.
 */
final class SoftDeletedFilter extends SQLFilter
{
    public const string NAME = 'phil_soft_deleted';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!is_subclass_of($targetEntity->getName(), SoftDeletable::class) || !$targetEntity->hasField('deletedAt')) {
            return '';
        }

        return \sprintf('%s.%s IS NULL', $targetTableAlias, $targetEntity->getColumnName('deletedAt'));
    }
}
