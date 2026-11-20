<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * The unit is archived, so read only until it is restored: no unit is created or moved under it, and its members stay
 * as they are.
 */
final class UnitArchived extends RuntimeException implements OrganizationsException
{
    public static function of(string $unitId): self
    {
        return new self(\sprintf('The unit "%s" is archived.', $unitId));
    }
}
