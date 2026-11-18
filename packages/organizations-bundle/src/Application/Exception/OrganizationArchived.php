<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * The organization is archived, so read only until it is restored.
 */
final class OrganizationArchived extends RuntimeException implements OrganizationsException
{
    public static function of(string $organizationId): self
    {
        return new self(\sprintf('The organization "%s" is archived.', $organizationId));
    }
}
