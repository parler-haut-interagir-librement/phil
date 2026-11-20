<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Contract;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\Model\UnitMembership;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitPath;

/**
 * Where the units of organizations and their members are kept, beside the organizations: the store of the bundle
 * implements both contracts, so that a change of units runs in the transaction of `OrganizationStore::transactional()`
 * and under the lock of its organization, and so that a member leaving an organization leaves its units.
 *
 * A unit's path names its ancestors; its descendants are the units whose path starts with its own. Every list of units
 * is sorted by path — a parent before its children —, which compares byte for byte.
 */
interface UnitStore
{
    public function unit(UnitId $id): ?Unit;

    /**
     * Adds the unit, or writes its name and whether it is archived; a move goes through `moveUnit()`.
     */
    public function saveUnit(Unit $unit): void;

    /**
     * Writes the new place of a unit that moved, and the paths of every unit below it.
     *
     * @param UnitPath $from the path the unit had
     */
    public function moveUnit(Unit $unit, UnitPath $from): void;

    /**
     * @param UnitPath|null $within the subtree, its root included; the whole organization when null
     *
     * @return list<Unit> sorted by path
     */
    public function units(OrganizationId $organizationId, ?UnitPath $within = null, bool $withArchived = true): array;

    /**
     * @param UnitId|null $parentId the parent; the roots when null
     *
     * @return list<Unit> sorted by path
     */
    public function children(OrganizationId $organizationId, ?UnitId $parentId, bool $withArchived = true): array;

    /**
     * @param list<UnitId> $ids
     *
     * @return list<Unit> those found, sorted by path
     */
    public function unitsById(array $ids): array;

    public function countUnits(OrganizationId $organizationId): int;

    /**
     * @return int the depth of the deepest unit of the subtree, its root included
     */
    public function deepest(OrganizationId $organizationId, UnitPath $within): int;

    /**
     * Archives at `$now` the units of the subtree that are not archived.
     *
     * @return list<UnitId> the units archived, sorted by path
     */
    public function archiveUnits(OrganizationId $organizationId, UnitPath $within, DateTimeImmutable $now): array;

    /**
     * Restores the units of the subtree archived at `$archivedAt`, to the second.
     *
     * @return list<UnitId> the units restored, sorted by path
     */
    public function restoreUnits(OrganizationId $organizationId, UnitPath $within, DateTimeImmutable $archivedAt): array;

    public function unitMembership(UnitId $unitId, MemberId $memberId): ?UnitMembership;

    /**
     * @param int<1, 1000> $limit
     *
     * @return list<UnitMembership> sorted by member, after `$afterMember` when it is given
     */
    public function unitMemberships(UnitId $unitId, int $limit, ?string $afterMember = null): array;

    /**
     * @return list<UnitMembership> the member's unit memberships, of one organization when it is given, sorted by unit
     */
    public function unitMembershipsOf(MemberId $memberId, ?OrganizationId $organizationId = null): array;

    /**
     * @param list<UnitId> $unitIds the units of a path, at most sixteen
     *
     * @return list<UnitMembership> the member's memberships of those units
     */
    public function unitMembershipsAmong(array $unitIds, MemberId $memberId): array;

    /**
     * @param int<1, 1000> $limit
     *
     * @return list<MemberId> the members of the units of the subtree, each once, sorted, after `$afterMember` when it is
     *                        given
     */
    public function membersWithin(OrganizationId $organizationId, UnitPath $within, int $limit, ?string $afterMember = null): array;

    /**
     * Adds the unit membership, or writes its roles.
     */
    public function saveUnitMembership(UnitMembership $membership): void;

    public function deleteUnitMembership(UnitId $unitId, MemberId $memberId): bool;
}
