<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * No team of that identifier in the organization.
 */
final class UnknownTeam extends RuntimeException implements OrganizationsException
{
    public static function named(string $team): self
    {
        return new self(\sprintf('No team "%s".', $team));
    }
}
