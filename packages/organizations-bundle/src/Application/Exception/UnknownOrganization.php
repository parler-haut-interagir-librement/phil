<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * No organization of that identifier or slug.
 */
final class UnknownOrganization extends RuntimeException implements OrganizationsException
{
    public static function named(string $organization): self
    {
        return new self(\sprintf('No organization "%s".', $organization));
    }
}
