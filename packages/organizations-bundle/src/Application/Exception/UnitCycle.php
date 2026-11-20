<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * A unit cannot move under itself or under one of the units below it.
 */
final class UnitCycle extends RuntimeException implements OrganizationsException
{
    public static function of(string $unitId, string $parentId): self
    {
        return new self(\sprintf('The unit "%s" cannot move under "%s", which is itself or below it.', $unitId, $parentId));
    }
}
