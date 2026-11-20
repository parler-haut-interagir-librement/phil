<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Contract\UnitStore;
use Phil\OrganizationsBundle\Application\Exception\AlreadyMember;
use Phil\OrganizationsBundle\Application\Exception\NotAMember;
use Phil\OrganizationsBundle\Application\Exception\OrganizationArchived;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\Exception\UnitArchived;
use Phil\OrganizationsBundle\Application\Exception\UnitCycle;
use Phil\OrganizationsBundle\Application\Exception\UnknownUnit;
use Phil\OrganizationsBundle\Domain\Event\UnitArchived as UnitArchivedEvent;
use Phil\OrganizationsBundle\Domain\Event\UnitCreated;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberAdded;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberRemoved;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Event\UnitMoved;
use Phil\OrganizationsBundle\Domain\Event\UnitRenamed;
use Phil\OrganizationsBundle\Domain\Event\UnitRestored;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\Model\UnitMembership;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitPath;
use Psr\Clock\ClockInterface;

/**
 * The units of an organization, in a tree, and their members.
 *
 * A member who holds `units.manage` in the organization creates root units and manages every unit. A member whose unit
 * roles, in a unit or above it, grant `unit.manage` — the `manager` — creates units below it, renames it and the units
 * below, moves, archives and restores the units below it, and manages their members, granting only unit roles whose
 * permissions they hold there. A unit moves or is archived by whoever manages its parent, and moves only where they
 * manage too. Every member of a unit is a member of the organization; with `$singleMembership`, of one unit of it at
 * most. Each change locks the organization, so that two moves never make a cycle together.
 */
final readonly class Units
{
    public function __construct(
        private OrganizationStore $store,
        private UnitStore $units,
        private UnitOfWork $work,
        private OrganizationAccess $access,
        private UnitAccess $unitAccess,
        private ClockInterface $clock,
        private bool $singleMembership = false,
    ) {
    }

    /**
     * @throws UnknownUnit when there is none
     */
    public function get(UnitId $id): Unit
    {
        return $this->units->unit($id) ?? throw UnknownUnit::named($id->value);
    }

    /**
     * @param UnitId|null $parentId the unit it goes under; a root when null
     *
     * @throws InvalidOrganization when the organization holds the most units, or the unit would be too deep
     */
    public function create(OrganizationId $organizationId, MemberId $actor, string $name, ?UnitId $parentId = null): Unit
    {
        $unitName = OrganizationName::fromString($name);

        return $this->work->run(function (EventRecorder $events) use ($organizationId, $actor, $unitName, $parentId): Unit {
            $organization = $this->organization($organizationId, $actor);
            $parent = $parentId instanceof UnitId ? $this->inOrganization($parentId, $organization) : null;
            $this->manage($organization, $parent, $actor);

            if ($this->units->countUnits($organizationId) >= Unit::MAX_PER_ORGANIZATION) {
                throw InvalidOrganization::because(\sprintf('An organization holds at most %d units.', Unit::MAX_PER_ORGANIZATION));
            }

            $unit = Unit::create(UnitId::generate(), $organizationId, $unitName, $parent, $this->clock->now());
            $this->units->saveUnit($unit);
            $events->record(new UnitCreated($organizationId->value, $unit->id->value, $parentId?->value, $actor->value));

            return $unit;
        });
    }

    public function rename(UnitId $unitId, MemberId $actor, string $name): Unit
    {
        $unitName = OrganizationName::fromString($name);

        return $this->work->run(function (EventRecorder $events) use ($unitId, $actor, $unitName): Unit {
            [$organization, $unit] = $this->locked($unitId, $actor);
            $this->manage($organization, $unit, $actor);

            if ($unit->name->value !== $unitName->value) {
                $unit->rename($unitName);
                $this->units->saveUnit($unit);
                $events->record(new UnitRenamed($organization->id->value, $unitId->value, $actor->value));
            }

            return $unit;
        });
    }

    /**
     * Moves the unit, with the units below it, under another unit, or to the root.
     *
     * @throws UnitCycle           when the parent is the unit or below it
     * @throws InvalidOrganization when a unit would be too deep
     */
    public function move(UnitId $unitId, MemberId $actor, ?UnitId $parentId): Unit
    {
        return $this->work->run(function (EventRecorder $events) use ($unitId, $actor, $parentId): Unit {
            [$organization, $unit] = $this->locked($unitId, $actor);
            $previousParentId = $unit->parentId();
            $this->manage($organization, $this->parentOf($unit), $actor);
            $this->active($unit);

            if ($previousParentId?->value === $parentId?->value) {
                return $unit;
            }

            $parent = $parentId instanceof UnitId ? $this->inOrganization($parentId, $organization) : null;

            if ($parent instanceof Unit && $parent->path->isWithin($unit->path)) {
                throw UnitCycle::of($unitId->value, $parent->id->value);
            }

            $this->manage($organization, $parent, $actor);
            $depth = $this->units->deepest($organization->id, $unit->path) - $unit->path->depth() + 1 + ($parent?->path->depth() ?? 0);

            if ($depth > UnitPath::MAX_DEPTH) {
                throw InvalidOrganization::because(\sprintf('A unit is at most %d levels deep.', UnitPath::MAX_DEPTH));
            }

            $from = $unit->moveUnder($parent);
            $this->units->moveUnit($unit, $from);
            $events->record(new UnitMoved($organization->id->value, $unitId->value, $previousParentId?->value, $parentId?->value, $actor->value));

            return $unit;
        });
    }

    /**
     * Archives the unit and the units below it; their members stay.
     *
     * @throws UnitArchived when the unit is archived already
     */
    public function archive(UnitId $unitId, MemberId $actor): Unit
    {
        return $this->work->run(function (EventRecorder $events) use ($unitId, $actor): Unit {
            [$organization, $unit] = $this->locked($unitId, $actor);
            $this->manage($organization, $this->parentOf($unit), $actor);
            $this->active($unit);

            $now = $this->clock->now();
            $archived = $this->units->archiveUnits($organization->id, $unit->path, $now);
            $unit->archive($now);
            $events->record(new UnitArchivedEvent($organization->id->value, $unitId->value, $this->ids($unitId, $archived), $actor->value));

            return $unit;
        });
    }

    /**
     * Restores the unit and the units below it that were archived with it.
     *
     * @throws UnitArchived when the unit's parent is archived
     */
    public function restore(UnitId $unitId, MemberId $actor): Unit
    {
        return $this->work->run(function (EventRecorder $events) use ($unitId, $actor): Unit {
            [$organization, $unit] = $this->locked($unitId, $actor);
            $this->manage($organization, $this->parentOf($unit), $actor);
            $archivedAt = $unit->archivedAt;

            if (null === $archivedAt) {
                return $unit;
            }

            $restored = $this->units->restoreUnits($organization->id, $unit->path, $archivedAt);
            $unit->restore();
            $events->record(new UnitRestored($organization->id->value, $unitId->value, $this->ids($unitId, $restored), $actor->value));

            return $unit;
        });
    }

    /**
     * @param iterable<string> $roles roles of the unit catalogue
     *
     * @throws NotAMember    when the member is not a member of the organization
     * @throws AlreadyMember when the member is in the unit already, or in another unit of the organization while a member
     *                       belongs to one unit at most
     */
    public function addMember(UnitId $unitId, MemberId $actor, MemberId $member, iterable $roles): UnitMembership
    {
        $granted = $this->unitAccess->roles()->check(Roles::of($roles));

        return $this->work->run(function (EventRecorder $events) use ($unitId, $actor, $member, $granted): UnitMembership {
            [$organization, $unit] = $this->locked($unitId, $actor);
            $this->bound($this->manage($organization, $unit, $actor), $granted);

            if (!$this->store->membership($organization->id, $member) instanceof Membership) {
                throw NotAMember::ofOrganization($organization->id->value);
            }

            if ($this->units->unitMembership($unitId, $member) instanceof UnitMembership) {
                throw AlreadyMember::ofUnit($unitId->value);
            }

            if ($this->singleMembership && [] !== $this->units->unitMembershipsOf($member, $organization->id)) {
                throw AlreadyMember::ofAnotherUnit($organization->id->value);
            }

            $membership = UnitMembership::join($unitId, $organization->id, $member, $granted, $this->clock->now());
            $this->units->saveUnitMembership($membership);
            $events->record(new UnitMemberAdded($organization->id->value, $unitId->value, $member->value, $granted->names, $actor->value));

            return $membership;
        });
    }

    /**
     * @param iterable<string> $roles roles of the unit catalogue
     */
    public function changeRoles(UnitId $unitId, MemberId $actor, MemberId $member, iterable $roles): UnitMembership
    {
        $roles = $this->unitAccess->roles()->check(Roles::of($roles));

        return $this->work->run(function (EventRecorder $events) use ($unitId, $actor, $member, $roles): UnitMembership {
            [$organization, $unit] = $this->locked($unitId, $actor);
            $held = $this->manage($organization, $unit, $actor);
            $membership = $this->units->unitMembership($unitId, $member) ?? throw NotAMember::ofUnit($unitId->value);
            $this->bound($held, $membership->roles);
            $this->bound($held, $roles);
            $previous = $membership->roles;

            if (!$previous->equals($roles)) {
                $membership->changeRoles($roles);
                $this->units->saveUnitMembership($membership);
                $events->record(new UnitMemberRolesChanged($organization->id->value, $unitId->value, $member->value, $previous->names, $roles->names, $actor->value));
            }

            return $membership;
        });
    }

    /**
     * Removes a member from the unit; a member removing themselves leaves it, an archived unit too.
     */
    public function removeMember(UnitId $unitId, MemberId $actor, MemberId $member): void
    {
        $this->work->run(function (EventRecorder $events) use ($unitId, $actor, $member): void {
            $leaves = $actor->equals($member);
            $held = null;

            if ($leaves) {
                $organization = $this->access->lock($this->get($unitId)->organizationId);
            } else {
                [$organization, $unit] = $this->locked($unitId, $actor);
                $held = $this->manage($organization, $unit, $actor);
            }

            $membership = $this->units->unitMembership($unitId, $member) ?? throw NotAMember::ofUnit($unitId->value);
            $this->bound($held, $membership->roles);

            $this->units->deleteUnitMembership($unitId, $member);
            $events->record(new UnitMemberRemoved($organization->id->value, $unitId->value, $member->value, $membership->roles->names, $leaves ? null : $actor->value));
        });
    }

    /**
     * The organization, locked, for an actor who is one of its members, while it is active.
     *
     * @throws NotAMember           when the actor is not a member
     * @throws OrganizationArchived when the organization is archived
     */
    private function organization(OrganizationId $organizationId, MemberId $actor): Organization
    {
        $organization = $this->access->lock($organizationId);
        $this->access->require($organization, $actor, Permission::VIEW);

        if (!$organization->isActive()) {
            throw OrganizationArchived::of($organizationId->value);
        }

        return $organization;
    }

    /**
     * The unit, read again once its organization is locked.
     *
     * @return array{Organization, Unit}
     */
    private function locked(UnitId $unitId, MemberId $actor): array
    {
        $organization = $this->organization($this->get($unitId)->organizationId, $actor);

        return [$organization, $this->get($unitId)];
    }

    /**
     * @throws UnknownUnit when the unit is of another organization
     */
    private function inOrganization(UnitId $unitId, Organization $organization): Unit
    {
        $unit = $this->units->unit($unitId);

        if (!$unit instanceof Unit || !$unit->organizationId->equals($organization->id)) {
            throw UnknownUnit::named($unitId->value);
        }

        return $unit;
    }

    private function parentOf(Unit $unit): ?Unit
    {
        $parentId = $unit->parentId();

        return $parentId instanceof UnitId ? $this->get($parentId) : null;
    }

    /**
     * Checks that the actor manages the scope — a unit, or the root level when null — and gives the permissions they hold
     * there.
     *
     * @return list<string>|null the actor's unit permissions in the scope; null when they hold `units.manage` and may
     *                           grant every unit role
     *
     * @throws UnitArchived     when the scope is archived
     * @throws PermissionDenied when the actor does not manage the scope
     */
    private function manage(Organization $organization, ?Unit $scope, MemberId $actor): ?array
    {
        if ($scope instanceof Unit) {
            $this->active($scope);
        }

        if (\in_array(Permission::UNITS, $this->access->permissions($organization->id, $actor), true)) {
            return null;
        }

        if (!$scope instanceof Unit) {
            throw PermissionDenied::lacking(Permission::UNITS);
        }

        $held = $this->unitAccess->inherited($scope, $actor);

        if (!\in_array(Permission::UNIT_MANAGE, $held, true)) {
            throw PermissionDenied::lacking(Permission::UNIT_MANAGE);
        }

        return $held;
    }

    private function active(Unit $unit): void
    {
        if (!$unit->isActive()) {
            throw UnitArchived::of($unit->id->value);
        }
    }

    /**
     * @param list<string>|null $held
     *
     * @throws PermissionDenied when a unit manager would grant or touch roles wider than their own
     */
    private function bound(?array $held, Roles $roles): void
    {
        if (null !== $held && [] !== array_diff($this->unitAccess->roles()->permissionsOf($roles), $held)) {
            throw PermissionDenied::widerRoles();
        }
    }

    /**
     * @param list<UnitId> $changed
     *
     * @return non-empty-list<string> the unit, then the others changed with it
     */
    private function ids(UnitId $unitId, array $changed): array
    {
        $ids = [$unitId->value];

        foreach ($changed as $id) {
            if (!$id->equals($unitId)) {
                $ids[] = $id->value;
            }
        }

        return $ids;
    }
}
