<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Contract\UnitStore;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\Model\UnitMembership;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;

/**
 * What a member may do in a unit: the permissions of the unit roles they hold in the unit or in any unit above it, by
 * the unit catalogue of the configuration. A role granted on a unit holds in every unit below it.
 *
 * A member whose organization roles grant `units.manage` holds every permission of the unit catalogue in every unit.
 * Nobody holds a permission in a unit that is archived, nor in a unit of an archived organization, nor without being a
 * member of the organization.
 */
final readonly class UnitAccess
{
    public function __construct(
        private OrganizationStore $store,
        private UnitStore $units,
        private OrganizationAccess $access,
        private RoleCatalog $roles,
    ) {
    }

    public function roles(): RoleCatalog
    {
        return $this->roles;
    }

    public function can(Unit|UnitId $unit, MemberId $memberId, string $permission): bool
    {
        return \in_array($permission, $this->permissions($unit, $memberId), true);
    }

    /**
     * Whether the member holds the role in the unit or in a unit above it, the unit archived or not.
     */
    public function hasRole(Unit|UnitId $unit, MemberId $memberId, string $role): bool
    {
        $unit = $this->unit($unit);

        if (!$unit instanceof Unit) {
            return false;
        }

        return array_any($this->units->unitMembershipsAmong($unit->path->unitIds(), $memberId), static fn (UnitMembership $membership): bool => $membership->roles->has($role));
    }

    /**
     * @return list<string> the permissions the member holds in the unit now, sorted
     */
    public function permissions(Unit|UnitId $unit, MemberId $memberId): array
    {
        $unit = $this->unit($unit);

        if (!$unit instanceof Unit || !$unit->isActive()) {
            return [];
        }

        $organization = $this->store->organization($unit->organizationId);

        if (!$organization instanceof Organization || !$organization->isActive()) {
            return [];
        }

        $granted = $this->access->permissions($organization->id, $memberId);

        if ([] === $granted) {
            return [];
        }

        return \in_array(Permission::UNITS, $granted, true) ? $this->roles->allPermissions() : $this->inherited($unit, $memberId);
    }

    /**
     * @return list<string> the permissions of the unit roles the member holds in the unit and above it, sorted
     */
    public function inherited(Unit $unit, MemberId $memberId): array
    {
        $permissions = [];

        foreach ($this->units->unitMembershipsAmong($unit->path->unitIds(), $memberId) as $membership) {
            foreach ($this->roles->permissionsOf($membership->roles) as $permission) {
                $permissions[$permission] = $permission;
            }
        }

        ksort($permissions, \SORT_STRING);

        return array_values($permissions);
    }

    private function unit(Unit|UnitId $unit): ?Unit
    {
        return $unit instanceof Unit ? $unit : $this->units->unit($unit);
    }
}
