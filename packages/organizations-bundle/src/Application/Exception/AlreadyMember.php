<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * The member is already a member of the organization, or of the team.
 */
final class AlreadyMember extends RuntimeException implements OrganizationsException
{
    public static function ofOrganization(string $organizationId): self
    {
        return new self(\sprintf('Already a member of the organization "%s".', $organizationId));
    }

    public static function ofTeam(string $teamId): self
    {
        return new self(\sprintf('Already a member of the team "%s".', $teamId));
    }
}
