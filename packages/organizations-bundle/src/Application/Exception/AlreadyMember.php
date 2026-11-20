<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * The member is already a member of the organization, of the team or of the unit, or, when a member belongs to one unit
 * of an organization at most, of another unit.
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

    public static function ofUnit(string $unitId): self
    {
        return new self(\sprintf('Already a member of the unit "%s".', $unitId));
    }

    public static function ofAnotherUnit(string $organizationId): self
    {
        return new self(\sprintf('Already a member of another unit of the organization "%s", and a member belongs to one unit at most.', $organizationId));
    }
}
