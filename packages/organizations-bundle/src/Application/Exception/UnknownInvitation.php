<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * No invitation of that identifier in the organization.
 */
final class UnknownInvitation extends RuntimeException implements OrganizationsException
{
    public static function named(string $invitation): self
    {
        return new self(\sprintf('No invitation "%s".', $invitation));
    }
}
