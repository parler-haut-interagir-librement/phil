<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Application;

use Phil\OrganizationsBundle\Application\Contract\UnitStore;
use Phil\OrganizationsBundle\Application\Exception\UnknownUnit;
use Phil\OrganizationsBundle\Application\RoleCatalog;
use Phil\OrganizationsBundle\Application\UnitAccess;
use Phil\OrganizationsBundle\Application\UnitTree;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\Model\UnitMembership;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A role granted on a unit holds in every unit below it; `units.manage` in the organization holds every unit
 * permission; nothing holds in an archived unit or organization, nor for a stranger. The tree answers the questions of
 * other modules.
 */
#[CoversClass(UnitAccess::class)]
#[CoversClass(UnitTree::class)]
#[CoversClass(RoleCatalog::class)]
final class UnitAccessTest extends TestCase
{
    private Stage $stage;

    private Organization $acme;

    private Unit $sales;

    private Unit $east;

    private Unit $north;

    private Unit $support;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->acme = $this->stage->acme();
        $this->stage->members->add($this->acme->id, Stage::member('ann'), Stage::member('dan'), ['member']);
        $this->sales = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Sales');
        $this->east = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'East', $this->sales->id);
        $this->north = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'North', $this->east->id);
        $this->support = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Support');
        $this->stage->units->addMember($this->sales->id, Stage::member('ann'), Stage::member('cam'), ['manager']);
        $this->stage->units->addMember($this->east->id, Stage::member('ann'), Stage::member('dan'), ['instructor']);
        $this->stage->units->addMember($this->north->id, Stage::member('ann'), Stage::member('dan'), ['member']);
    }

    public function testTheBuiltInRolesOfAUnit(): void
    {
        $roles = RoleCatalog::unit();

        self::assertSame(['manager', 'member'], $roles->names());
        self::assertSame(['unit.manage'], $roles->allPermissions());
        self::assertSame(['manager', 'member', 'instructor'], $this->stage->unitRoles->names());
        self::assertSame(['reports.read', 'unit.manage'], $this->stage->unitRoles->permissionsOf(Roles::of(['manager'])));
        self::assertSame($this->stage->unitRoles, $this->stage->unitAccess->roles());
    }

    public function testARoleHoldsInTheUnitAndBelow(): void
    {
        $access = $this->stage->unitAccess;

        self::assertSame(['reports.read', 'unit.manage'], $access->permissions($this->sales, Stage::member('cam')));
        self::assertSame(['reports.read', 'unit.manage'], $access->permissions($this->north->id, Stage::member('cam')));
        self::assertSame([], $access->permissions($this->support, Stage::member('cam')));
        self::assertSame([], $access->permissions($this->sales, Stage::member('dan')));
        self::assertSame(['courses.assign'], $access->permissions($this->east, Stage::member('dan')));
        self::assertSame(['courses.assign'], $access->permissions($this->north, Stage::member('dan')));
        self::assertTrue($access->can($this->north, Stage::member('dan'), 'courses.assign'));
        self::assertFalse($access->can($this->north, Stage::member('dan'), 'unit.manage'));
        self::assertTrue($access->can($this->north->id, Stage::member('cam'), 'unit.manage'));

        self::assertTrue($access->hasRole($this->north, Stage::member('cam'), 'manager'));
        self::assertTrue($access->hasRole($this->north->id, Stage::member('dan'), 'instructor'));
        self::assertTrue($access->hasRole($this->north, Stage::member('dan'), 'member'));
        self::assertFalse($access->hasRole($this->east, Stage::member('dan'), 'member'));
        self::assertFalse($access->hasRole($this->support, Stage::member('cam'), 'manager'));
        self::assertFalse($access->hasRole(UnitId::fromString('nowhere'), Stage::member('cam'), 'manager'));
    }

    public function testUnitsManageHoldsEveryUnitPermission(): void
    {
        self::assertSame(['courses.assign', 'reports.read', 'unit.manage'], $this->stage->unitAccess->permissions($this->support, Stage::member('bob')));
        self::assertSame(['courses.assign', 'reports.read', 'unit.manage'], $this->stage->unitAccess->permissions($this->north, Stage::member('ann')));
        self::assertFalse($this->stage->unitAccess->hasRole($this->north, Stage::member('ann'), 'manager'));
    }

    public function testNothingHoldsForAStrangerInAnArchivedUnitOrOrganization(): void
    {
        $access = $this->stage->unitAccess;

        self::assertSame([], $access->permissions($this->sales, Stage::member('zed')));
        self::assertSame([], $access->permissions(UnitId::fromString('nowhere'), Stage::member('cam')));

        $this->stage->units->archive($this->east->id, Stage::member('ann'));
        self::assertSame([], $access->permissions($this->east->id, Stage::member('cam')));
        self::assertSame([], $access->permissions($this->north->id, Stage::member('bob')));
        self::assertTrue($access->hasRole($this->north->id, Stage::member('dan'), 'instructor'));
        self::assertSame(['reports.read', 'unit.manage'], $access->permissions($this->sales->id, Stage::member('cam')));

        $this->stage->organizations->archive($this->acme->id, Stage::member('ann'));
        self::assertSame([], $access->permissions($this->sales->id, Stage::member('cam')));
        self::assertSame([], $access->permissions($this->support->id, Stage::member('ann')));
    }

    public function testAUnitWhoseOrganizationIsGoneGrantsNothing(): void
    {
        $orphan = Unit::create(UnitId::fromString('orphan'), OrganizationId::fromString('gone'), OrganizationName::fromString('Orphan'), null, $this->stage->clock->now());
        $this->stage->unitStore->saveUnit($orphan);
        $this->stage->unitStore->saveUnitMembership(UnitMembership::join($orphan->id, $orphan->organizationId, Stage::member('cam'), Roles::of(['manager']), $this->stage->clock->now()));

        self::assertSame([], $this->stage->unitAccess->permissions($orphan, Stage::member('cam')));
        self::assertSame(['reports.read', 'unit.manage'], $this->stage->unitAccess->inherited($orphan, Stage::member('cam')));
    }

    public function testAStrangerWithAStrayUnitMembershipHoldsNothing(): void
    {
        $this->stage->unitStore->saveUnitMembership(UnitMembership::join($this->sales->id, $this->acme->id, Stage::member('zed'), Roles::of(['manager']), $this->stage->clock->now()));

        self::assertSame([], $this->stage->unitAccess->permissions($this->north, Stage::member('zed')));
        self::assertSame(['reports.read', 'unit.manage'], $this->stage->unitAccess->inherited($this->north, Stage::member('zed')));
    }

    public function testThePermissionsOfSeveralUnitsAreSortedTogether(): void
    {
        $this->stage->units->addMember($this->sales->id, Stage::member('ann'), Stage::member('dan'), ['manager']);

        self::assertSame(['courses.assign', 'reports.read', 'unit.manage'], $this->stage->unitAccess->inherited($this->north, Stage::member('dan')));
    }

    public function testTheMembersAreReadAHundredAtATime(): void
    {
        $store = $this->createMock(UnitStore::class);
        $store->expects(self::once())->method('unitMemberships')->with($this->east->id, 100, null)->willReturn([]);
        new UnitTree($store)->members($this->east->id);

        $store = $this->createMock(UnitStore::class);
        $store->method('unit')->willReturn($this->east);
        $store->expects(self::once())->method('membersWithin')->with($this->acme->id, $this->east->path, 100, null)->willReturn([]);
        new UnitTree($store)->membersWithin($this->east->id);
    }

    public function testTheUnitsOfTheAncestorsAreAskedForAsAList(): void
    {
        $store = $this->createMock(UnitStore::class);
        $store->method('unitMembershipsOf')->willReturn([UnitMembership::join($this->north->id, $this->acme->id, Stage::member('dan'), Roles::of(['member']), $this->stage->clock->now())]);
        $store->expects(self::once())->method('unitsById')->with(self::callback(static fn (array $ids): bool => array_is_list($ids)))->willReturn([$this->north]);

        self::assertCount(3, new UnitTree($store)->unitIdsOf($this->acme->id, Stage::member('dan'), true));
    }

    public function testTheTree(): void
    {
        $tree = $this->stage->tree;
        $ids = static fn (array $units): array => array_map(static fn (Unit $unit): string => $unit->id->value, $units);

        self::assertEquals($this->east, $tree->unit($this->east->id));
        self::assertNull($tree->unit(UnitId::fromString('nowhere')));
        self::assertEquals($this->east, $tree->get($this->east->id));
        self::assertSame($ids([$this->sales, $this->east, $this->north, $this->support]), $ids($tree->units($this->acme->id)));
        self::assertSame($ids([$this->sales, $this->support]), $ids($tree->roots($this->acme->id)));
        self::assertSame($ids([$this->east]), $ids($tree->children($this->sales->id)));
        self::assertSame($ids([$this->sales, $this->east]), $ids($tree->ancestors($this->north->id)));
        self::assertSame([], $tree->ancestors($this->sales->id));
        self::assertSame($ids([$this->east, $this->north]), $ids($tree->descendants($this->sales->id)));
        self::assertSame($ids([$this->sales, $this->east, $this->north]), $ids($tree->descendants($this->sales->id, true)));
        self::assertTrue($tree->isWithin($this->north->id, $this->sales->id));
        self::assertTrue($tree->isWithin($this->sales->id, $this->sales->id));
        self::assertFalse($tree->isWithin($this->sales->id, $this->north->id));
        self::assertFalse($tree->isWithin(UnitId::fromString('nowhere'), $this->sales->id));

        $this->stage->units->archive($this->north->id, Stage::member('ann'));
        $this->stage->units->archive($this->support->id, Stage::member('ann'));
        self::assertSame($ids([$this->sales, $this->east]), $ids($tree->units($this->acme->id)));
        self::assertCount(4, $tree->units($this->acme->id, true));
        self::assertSame($ids([$this->sales]), $ids($tree->roots($this->acme->id)));
        self::assertCount(2, $tree->roots($this->acme->id, true));
        self::assertSame([], $tree->children($this->east->id));
        self::assertSame($ids([$this->north]), $ids($tree->children($this->east->id, true)));
        self::assertSame($ids([$this->east]), $ids($tree->descendants($this->sales->id)));
        self::assertSame($ids([$this->east, $this->north]), $ids($tree->descendants($this->sales->id, false, true)));
        self::assertSame($ids([$this->sales, $this->east]), $ids($tree->ancestors($this->north->id)));

        $this->expectException(UnknownUnit::class);

        $tree->get(UnitId::fromString('nowhere'));
    }

    public function testTheMembersOfTheTree(): void
    {
        $tree = $this->stage->tree;
        $names = static fn (array $members): array => array_map(static fn (MemberId $member): string => $member->value, $members);

        self::assertSame(['dan'], array_map(static fn (UnitMembership $membership): string => $membership->memberId->value, $tree->members($this->east->id)));
        self::assertSame(['cam', 'dan'], $names($tree->membersWithin($this->sales->id)));
        self::assertSame(['cam'], $names($tree->membersWithin($this->sales->id, 1)));
        self::assertSame(['dan'], $names($tree->membersWithin($this->sales->id, 100, 'cam')));
        self::assertSame(['dan'], $names($tree->membersWithin($this->east->id)));
        self::assertSame([], $names($tree->membersWithin($this->support->id)));
        self::assertSame([$this->east->id->value, $this->north->id->value], array_map(static fn (UnitMembership $membership): string => $membership->unitId->value, $tree->membershipsOf($this->acme->id, Stage::member('dan'))));

        $sorted = [$this->east->id->value, $this->north->id->value];
        sort($sorted);
        self::assertSame($sorted, array_map(static fn (UnitId $id): string => $id->value, $tree->unitIdsOf($this->acme->id, Stage::member('dan'))));
        $all = [$this->sales->id->value, $this->east->id->value, $this->north->id->value];
        sort($all);
        self::assertSame($all, array_map(static fn (UnitId $id): string => $id->value, $tree->unitIdsOf($this->acme->id, Stage::member('dan'), true)));
        self::assertSame([], $tree->unitIdsOf($this->acme->id, Stage::member('zed'), true));
    }
}
