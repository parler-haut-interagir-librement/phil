<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Phil\DoctrineBehaviours\Contract\Deactivatable;

/**
 * Hides the deactivated rows of `Deactivatable` entities from every query while it is enabled; disable it to read
 * them. The value compared is the database's own spelling of true.
 */
final class DeactivatedFilter extends SQLFilter
{
    public const string NAME = 'phil_deactivated';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!is_subclass_of($targetEntity->getName(), Deactivatable::class) || !$targetEntity->hasField('active')) {
            return '';
        }

        /** @var int|string $true */
        $true = $this->getConnection()->getDatabasePlatform()->convertBooleans(true);

        return \sprintf('%s.%s = %s', $targetTableAlias, $targetEntity->getColumnName('active'), $true);
    }
}
