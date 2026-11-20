<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Model;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\Moment;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;

/**
 * One member of one unit, with one or more roles of the unit catalogue, which hold in the unit and in every unit below
 * it.
 */
final class UnitMembership
{
    private function __construct(
        public readonly UnitId $unitId,
        public readonly OrganizationId $organizationId,
        public readonly MemberId $memberId,
        public private(set) Roles $roles,
        public readonly DateTimeImmutable $joinedAt,
    ) {
    }

    public static function join(UnitId $unitId, OrganizationId $organizationId, MemberId $memberId, Roles $roles, DateTimeImmutable $now): self
    {
        return new self($unitId, $organizationId, $memberId, $roles, Moment::of($now));
    }

    /**
     * A unit membership read back from a store.
     */
    public static function restored(UnitId $unitId, OrganizationId $organizationId, MemberId $memberId, Roles $roles, DateTimeImmutable $joinedAt): self
    {
        return new self($unitId, $organizationId, $memberId, $roles, $joinedAt);
    }

    public function changeRoles(Roles $roles): void
    {
        $this->roles = $roles;
    }
}
