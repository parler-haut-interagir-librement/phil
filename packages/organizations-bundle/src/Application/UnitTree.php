<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Application\Contract\UnitStore;
use Phil\OrganizationsBundle\Application\Exception\UnknownUnit;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\Model\UnitMembership;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;

/**
 * The questions other modules ask of the tree of units, read only and without permission checks: a unit, its ancestors,
 * its children, its descendants, its members, and the units of a member. Every list of units is sorted by path, a
 * parent before its children; archived units are left out unless asked for.
 *
 * Each answer costs one query of the store, or two when it starts from a unit identifier: a unit's ancestors are named
 * by its path, and its descendants are one range of an index.
 */
final readonly class UnitTree
{
    public function __construct(
        private UnitStore $units,
    ) {
    }

    public function unit(UnitId $id): ?Unit
    {
        return $this->units->unit($id);
    }

    /**
     * @throws UnknownUnit when there is none
     */
    public function get(UnitId $id): Unit
    {
        return $this->units->unit($id) ?? throw UnknownUnit::named($id->value);
    }

    /**
     * @return list<Unit> every unit of the organization, sorted by path
     */
    public function units(OrganizationId $organizationId, bool $withArchived = false): array
    {
        return $this->units->units($organizationId, null, $withArchived);
    }

    /**
     * @return list<Unit>
     */
    public function roots(OrganizationId $organizationId, bool $withArchived = false): array
    {
        return $this->units->children($organizationId, null, $withArchived);
    }

    /**
     * @return list<Unit>
     */
    public function children(UnitId $id, bool $withArchived = false): array
    {
        return $this->units->children($this->get($id)->organizationId, $id, $withArchived);
    }

    /**
     * @return list<Unit> the units above, from the root, archived or not
     */
    public function ancestors(UnitId $id): array
    {
        return $this->units->unitsById($this->get($id)->path->ancestorIds());
    }

    /**
     * @return list<Unit> the units below, the unit first when `$withSelf`
     */
    public function descendants(UnitId $id, bool $withSelf = false, bool $withArchived = false): array
    {
        $unit = $this->get($id);
        $found = [];

        foreach ($this->units->units($unit->organizationId, $unit->path, $withArchived) as $descendant) {
            if ($withSelf || !$descendant->id->equals($id)) {
                $found[] = $descendant;
            }
        }

        return $found;
    }

    /**
     * Whether the unit is `$ancestor` or below it.
     */
    public function isWithin(UnitId $unit, UnitId $ancestor): bool
    {
        return $this->units->unit($unit)?->path->contains($ancestor) ?? false;
    }

    /**
     * @param int<1, 1000> $limit
     *
     * @return list<UnitMembership> the members of the unit itself, sorted by member, after `$afterMember`
     */
    public function members(UnitId $id, int $limit = 100, ?string $afterMember = null): array
    {
        return $this->units->unitMemberships($id, $limit, $afterMember);
    }

    /**
     * @param int<1, 1000> $limit
     *
     * @return list<MemberId> the members of the unit and of the units below it, each once, sorted, after `$afterMember`
     */
    public function membersWithin(UnitId $id, int $limit = 100, ?string $afterMember = null): array
    {
        $unit = $this->get($id);

        return $this->units->membersWithin($unit->organizationId, $unit->path, $limit, $afterMember);
    }

    /**
     * @return list<UnitMembership> the member's unit memberships in the organization, sorted by unit
     */
    public function membershipsOf(OrganizationId $organizationId, MemberId $memberId): array
    {
        return $this->units->unitMembershipsOf($memberId, $organizationId);
    }

    /**
     * The units a member belongs to, and with `$withAncestors` the units above them: those whose catalogue, page or
     * report reaches the member.
     *
     * @return list<UnitId> sorted, each once
     */
    public function unitIdsOf(OrganizationId $organizationId, MemberId $memberId, bool $withAncestors = false): array
    {
        $ids = [];

        foreach ($this->units->unitMembershipsOf($memberId, $organizationId) as $membership) {
            $ids[$membership->unitId->value] = $membership->unitId;
        }

        if ($withAncestors) {
            foreach ($this->units->unitsById(array_values($ids)) as $unit) {
                foreach ($unit->path->ancestorIds() as $ancestor) {
                    $ids[$ancestor->value] = $ancestor;
                }
            }
        }

        ksort($ids, \SORT_STRING);

        return array_values($ids);
    }
}
