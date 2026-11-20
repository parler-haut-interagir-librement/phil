<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Application;

use LogicException;
use Phil\OrganizationsBundle\Application\Contract\UnitStore;
use Phil\OrganizationsBundle\Application\Exception\AlreadyMember;
use Phil\OrganizationsBundle\Application\Exception\NotAMember;
use Phil\OrganizationsBundle\Application\Exception\OrganizationArchived;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\Exception\UnitArchived;
use Phil\OrganizationsBundle\Application\Exception\UnitCycle;
use Phil\OrganizationsBundle\Application\Exception\UnknownRole;
use Phil\OrganizationsBundle\Application\Exception\UnknownUnit;
use Phil\OrganizationsBundle\Application\Units;
use Phil\OrganizationsBundle\Domain\Event\UnitArchived as UnitArchivedEvent;
use Phil\OrganizationsBundle\Domain\Event\UnitCreated;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberAdded;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberRemoved;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Event\UnitMoved;
use Phil\OrganizationsBundle\Domain\Event\UnitRenamed;
use Phil\OrganizationsBundle\Domain\Event\UnitRestored;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\Model\UnitMembership;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitPath;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Units::class)]
#[CoversClass(UnitArchived::class)]
#[CoversClass(UnitCycle::class)]
#[CoversClass(UnknownUnit::class)]
#[CoversClass(AlreadyMember::class)]
#[CoversClass(NotAMember::class)]
final class UnitsTest extends TestCase
{
    private Stage $stage;

    private Organization $acme;

    private Unit $sales;

    private Unit $east;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->acme = $this->stage->acme();
        $this->stage->members->add($this->acme->id, Stage::member('ann'), Stage::member('dan'), ['member']);
        $this->sales = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Sales');
        $this->east = $this->stage->units->create($this->acme->id, Stage::member('bob'), 'East', $this->sales->id);
        $this->stage->units->addMember($this->sales->id, Stage::member('ann'), Stage::member('cam'), ['manager']);
        $this->stage->events->clear();
    }

    public function testCreate(): void
    {
        $west = $this->stage->units->create($this->acme->id, Stage::member('cam'), ' West ', $this->sales->id);
        $north = $this->stage->units->create($this->acme->id, Stage::member('cam'), 'North', $this->east->id);

        self::assertSame('West', $west->name->value);
        self::assertSame('/'.$this->sales->id->value.'/'.$west->id->value.'/', $west->path->toString());
        self::assertSame(3, $north->path->depth());
        self::assertEquals($west, $this->stage->units->get($west->id));
        self::assertEquals([
            new UnitCreated($this->acme->id->value, $west->id->value, $this->sales->id->value, 'cam'),
            new UnitCreated($this->acme->id->value, $north->id->value, $this->east->id->value, 'cam'),
        ], $this->stage->events->events);

        $root = $this->stage->units->create($this->acme->id, Stage::member('bob'), 'Support');
        self::assertNull($root->parentId());
        self::assertEquals(new UnitCreated($this->acme->id->value, $root->id->value, null, 'bob'), $this->stage->events->events[2]);
    }

    public function testOnlyThoseWhoManageCreate(): void
    {
        self::assertSame('The permission "units.manage" is required.', $this->denied(fn (): Unit => $this->stage->units->create($this->acme->id, Stage::member('cam'), 'Mine')));
        self::assertSame('The permission "unit.manage" is required.', $this->denied(fn (): Unit => $this->stage->units->create($this->acme->id, Stage::member('dan'), 'Mine', $this->east->id)));

        $this->expectException(NotAMember::class);

        $this->stage->units->create($this->acme->id, Stage::member('zed'), 'Mine');
    }

    public function testAParentOfAnotherOrganizationIsUnknown(): void
    {
        $other = $this->stage->organizations->create('Other', Stage::member('ann'));

        try {
            $this->stage->units->create($other->id, Stage::member('ann'), 'Mine', $this->sales->id);
            self::fail('A unit went under a unit of another organization.');
        } catch (UnknownUnit $unknownUnit) {
            self::assertSame('No unit "'.$this->sales->id->value.'".', $unknownUnit->getMessage());
        }

        $this->expectException(UnknownUnit::class);

        $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Mine', UnitId::fromString('nowhere'));
    }

    public function testAnOrganizationHoldsTenThousandUnitsAtMost(): void
    {
        $count = Unit::MAX_PER_ORGANIZATION - 1;
        $store = self::createStub(UnitStore::class);
        $store->method('countUnits')->willReturnCallback(static function () use (&$count): int {
            return $count;
        });
        $units = new Units($this->stage->store, $store, $this->stage->work, $this->stage->access, $this->stage->unitAccess, $this->stage->clock);

        self::assertSame('Last', $units->create($this->acme->id, Stage::member('ann'), 'Last')->name->value);

        $count = Unit::MAX_PER_ORGANIZATION;

        $this->expectExceptionMessageIsOrContains('An organization holds at most 10000 units.');

        $units->create($this->acme->id, Stage::member('ann'), 'One too many');
    }

    public function testAnArchivedOrganizationChangesNoUnit(): void
    {
        $this->stage->organizations->archive($this->acme->id, Stage::member('ann'));

        foreach ([
            fn (): Unit => $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Mine'),
            fn (): Unit => $this->stage->units->rename($this->sales->id, Stage::member('ann'), 'Mine'),
            fn (): UnitMembership => $this->stage->units->addMember($this->sales->id, Stage::member('ann'), Stage::member('dan'), ['member']),
        ] as $change) {
            try {
                $change();
                self::fail('An archived organization changed.');
            } catch (OrganizationArchived $organizationArchived) {
                self::assertSame('The organization "'.$this->acme->id->value.'" is archived.', $organizationArchived->getMessage());
            }
        }
    }

    public function testRename(): void
    {
        $renamed = $this->stage->units->rename($this->east->id, Stage::member('cam'), 'East coast');
        $this->stage->units->rename($this->east->id, Stage::member('cam'), 'East coast');

        self::assertSame('East coast', $renamed->name->value);
        self::assertSame('East coast', $this->stage->units->get($this->east->id)->name->value);
        self::assertEquals([new UnitRenamed($this->acme->id->value, $this->east->id->value, 'cam')], $this->stage->events->events);
        self::assertSame('The permission "unit.manage" is required.', $this->denied(fn (): Unit => $this->stage->units->rename($this->east->id, Stage::member('dan'), 'Mine')));

        $this->expectException(UnknownUnit::class);

        $this->stage->units->rename(UnitId::fromString('nowhere'), Stage::member('ann'), 'Mine');
    }

    public function testMoveWithTheUnitsBelow(): void
    {
        $north = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'North', $this->east->id);
        $support = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Support');
        $this->stage->events->clear();

        $moved = $this->stage->units->move($this->east->id, Stage::member('ann'), $support->id);

        self::assertSame('/'.$support->id->value.'/'.$this->east->id->value.'/', $moved->path->toString());
        self::assertSame('/'.$support->id->value.'/'.$this->east->id->value.'/'.$north->id->value.'/', $this->stage->units->get($north->id)->path->toString());
        self::assertEquals([new UnitMoved($this->acme->id->value, $this->east->id->value, $this->sales->id->value, $support->id->value, 'ann')], $this->stage->events->events);

        $this->stage->units->move($this->east->id, Stage::member('ann'), null);
        self::assertSame('/'.$this->east->id->value.'/'.$north->id->value.'/', $this->stage->units->get($north->id)->path->toString());
        self::assertEquals(new UnitMoved($this->acme->id->value, $this->east->id->value, $support->id->value, null, 'ann'), $this->stage->events->events[1]);
    }

    public function testAMoveToTheSameParentChangesNothing(): void
    {
        self::assertEquals($this->east, $this->stage->units->move($this->east->id, Stage::member('cam'), $this->sales->id));
        self::assertEquals($this->sales, $this->stage->units->move($this->sales->id, Stage::member('ann'), null));
        self::assertSame([], $this->stage->events->events);
    }

    public function testACycleIsRefused(): void
    {
        $north = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'North', $this->east->id);

        foreach ([$this->sales->id, $this->east->id, $north->id] as $parent) {
            try {
                $this->stage->units->move($this->sales->id, Stage::member('ann'), $parent);
                self::fail('A unit moved under itself.');
            } catch (UnitCycle $unitCycle) {
                self::assertSame(\sprintf('The unit "%s" cannot move under "%s", which is itself or below it.', $this->sales->id->value, $parent->value), $unitCycle->getMessage());
            }
        }

        self::assertSame('/'.$this->sales->id->value.'/', $this->stage->units->get($this->sales->id)->path->toString());
    }

    public function testAMoveKeepsTheTreeSixteenLevelsDeep(): void
    {
        $parent = $this->sales;

        for ($level = 2; $level <= UnitPath::MAX_DEPTH; ++$level) {
            $parent = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Level '.$level, $parent->id);
        }

        $support = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Support');
        $deep = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Deep', $support->id);

        try {
            $this->stage->units->move($this->sales->id, Stage::member('ann'), $support->id);
            self::fail('A move went seventeen levels deep.');
        } catch (InvalidOrganization $invalidOrganization) {
            self::assertSame('A unit is at most 16 levels deep.', $invalidOrganization->getMessage());
        }

        $level15 = $this->stage->units->get($parent->parentId() ?? throw new LogicException('No parent.'));
        $this->stage->units->move($deep->id, Stage::member('ann'), $level15->id);
        self::assertSame(16, $this->stage->units->get($deep->id)->path->depth());

        $this->expectExceptionMessageIsOrContains('A unit is at most 16 levels deep.');

        $this->stage->units->move($support->id, Stage::member('ann'), $parent->id);
    }

    public function testAMoveNeedsTheParentAndTheTargetManaged(): void
    {
        $support = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Support');
        $west = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'West', $this->sales->id);
        $this->stage->units->addMember($this->east->id, Stage::member('ann'), Stage::member('dan'), ['manager']);

        self::assertSame('The permission "unit.manage" is required.', $this->denied(fn (): Unit => $this->stage->units->move($this->east->id, Stage::member('dan'), $west->id)));
        self::assertSame('The permission "units.manage" is required.', $this->denied(fn (): Unit => $this->stage->units->move($this->sales->id, Stage::member('cam'), $support->id)));
        self::assertSame('The permission "unit.manage" is required.', $this->denied(fn (): Unit => $this->stage->units->move($this->east->id, Stage::member('cam'), $support->id)));
        self::assertSame('The permission "units.manage" is required.', $this->denied(fn (): Unit => $this->stage->units->move($this->east->id, Stage::member('cam'), null)));

        $this->stage->units->move($this->east->id, Stage::member('cam'), $west->id);
        self::assertSame($west->id->value, $this->stage->units->get($this->east->id)->parentId()?->value);
    }

    public function testArchiveAndRestoreWithTheUnitsBelow(): void
    {
        $north = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'North', $this->east->id);
        $this->stage->clock->modify('+1 hour');
        $this->stage->units->archive($north->id, Stage::member('cam'));
        $this->stage->clock->modify('+1 hour');
        $this->stage->events->clear();

        $archived = $this->stage->units->archive($this->sales->id, Stage::member('ann'));

        self::assertFalse($archived->isActive());
        self::assertSame([], $this->stage->tree->units($this->acme->id));
        self::assertEquals([new UnitArchivedEvent($this->acme->id->value, $this->sales->id->value, [$this->sales->id->value, $this->east->id->value], 'ann')], $this->stage->events->events);

        try {
            $this->stage->units->archive($this->sales->id, Stage::member('ann'));
            self::fail('A unit was archived twice.');
        } catch (UnitArchived $unitArchived) {
            self::assertSame('The unit "'.$this->sales->id->value.'" is archived.', $unitArchived->getMessage());
        }

        try {
            $this->stage->units->restore($this->east->id, Stage::member('ann'));
            self::fail('A unit was restored under an archived one.');
        } catch (UnitArchived $unitArchived) {
            self::assertSame('The unit "'.$this->sales->id->value.'" is archived.', $unitArchived->getMessage());
        }

        $this->stage->events->clear();
        $restored = $this->stage->units->restore($this->sales->id, Stage::member('ann'));

        self::assertTrue($restored->isActive());
        self::assertSame([$this->sales->id->value, $this->east->id->value], array_map(static fn (Unit $unit): string => $unit->id->value, $this->stage->tree->units($this->acme->id)));
        self::assertFalse($this->stage->units->get($north->id)->isActive());
        self::assertEquals([new UnitRestored($this->acme->id->value, $this->sales->id->value, [$this->sales->id->value, $this->east->id->value], 'ann')], $this->stage->events->events);

        self::assertEquals($restored, $this->stage->units->restore($this->sales->id, Stage::member('ann')));
        self::assertCount(1, $this->stage->events->events);
    }

    public function testAnArchivedUnitIsReadOnly(): void
    {
        $this->stage->units->archive($this->east->id, Stage::member('cam'));
        $support = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Support');

        foreach ([
            fn (): Unit => $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Mine', $this->east->id),
            fn (): Unit => $this->stage->units->rename($this->east->id, Stage::member('ann'), 'Mine'),
            fn (): Unit => $this->stage->units->move($this->east->id, Stage::member('ann'), $support->id),
            fn (): Unit => $this->stage->units->move($support->id, Stage::member('ann'), $this->east->id),
            fn (): UnitMembership => $this->stage->units->addMember($this->east->id, Stage::member('ann'), Stage::member('dan'), ['member']),
        ] as $change) {
            try {
                $change();
                self::fail('An archived unit changed.');
            } catch (UnitArchived $unitArchived) {
                self::assertSame('The unit "'.$this->east->id->value.'" is archived.', $unitArchived->getMessage());
            }
        }
    }

    public function testAMemberAddedChangedAndRemoved(): void
    {
        $added = $this->stage->units->addMember($this->east->id, Stage::member('bob'), Stage::member('dan'), ['member', 'instructor']);

        self::assertSame(['instructor', 'member'], $added->roles->names);
        self::assertSame($this->acme->id->value, $added->organizationId->value);
        self::assertEquals($added, $this->stage->tree->members($this->east->id)[0]);

        $changed = $this->stage->units->changeRoles($this->east->id, Stage::member('bob'), Stage::member('dan'), ['member']);
        $this->stage->units->changeRoles($this->east->id, Stage::member('bob'), Stage::member('dan'), ['member']);
        self::assertSame(['member'], $changed->roles->names);

        $this->stage->units->removeMember($this->east->id, Stage::member('bob'), Stage::member('dan'));
        self::assertSame([], $this->stage->tree->members($this->east->id));

        self::assertEquals([
            new UnitMemberAdded($this->acme->id->value, $this->east->id->value, 'dan', ['instructor', 'member'], 'bob'),
            new UnitMemberRolesChanged($this->acme->id->value, $this->east->id->value, 'dan', ['instructor', 'member'], ['member'], 'bob'),
            new UnitMemberRemoved($this->acme->id->value, $this->east->id->value, 'dan', ['member'], 'bob'),
        ], $this->stage->events->events);
    }

    public function testAMemberLeavesAUnitAnArchivedOneToo(): void
    {
        $this->stage->units->addMember($this->east->id, Stage::member('cam'), Stage::member('dan'), ['member']);
        $this->stage->units->archive($this->east->id, Stage::member('cam'));
        $this->stage->events->clear();

        $this->stage->units->removeMember($this->east->id, Stage::member('dan'), Stage::member('dan'));

        self::assertNull($this->stage->unitStore->unitMembership($this->east->id, Stage::member('dan')));
        self::assertEquals([new UnitMemberRemoved($this->acme->id->value, $this->east->id->value, 'dan', ['member'])], $this->stage->events->events);

        $this->expectException(NotAMember::class);

        $this->stage->units->removeMember($this->east->id, Stage::member('dan'), Stage::member('dan'));
    }

    public function testOnlyMembersOfTheOrganizationJoinAUnitOnce(): void
    {
        try {
            $this->stage->units->addMember($this->east->id, Stage::member('cam'), Stage::member('zed'), ['member']);
            self::fail('A stranger joined a unit.');
        } catch (NotAMember $notaMember) {
            self::assertSame('Not a member of the organization "'.$this->acme->id->value.'".', $notaMember->getMessage());
        }

        try {
            $this->stage->units->addMember($this->sales->id, Stage::member('ann'), Stage::member('cam'), ['member']);
            self::fail('A member joined a unit twice.');
        } catch (AlreadyMember $alreadyMember) {
            self::assertSame('Already a member of the unit "'.$this->sales->id->value.'".', $alreadyMember->getMessage());
        }

        $this->stage->units->addMember($this->east->id, Stage::member('ann'), Stage::member('cam'), ['member']);
        self::assertCount(2, $this->stage->tree->membershipsOf($this->acme->id, Stage::member('cam')));

        try {
            $this->stage->units->changeRoles($this->east->id, Stage::member('ann'), Stage::member('dan'), ['member']);
            self::fail('The roles of a stranger to the unit changed.');
        } catch (NotAMember $notaMember) {
            self::assertSame('Not a member of the unit "'.$this->east->id->value.'".', $notaMember->getMessage());
        }

        $this->expectException(UnknownRole::class);

        $this->stage->units->addMember($this->east->id, Stage::member('ann'), Stage::member('dan'), ['owner']);
    }

    public function testAMemberBelongsToOneUnitWhenConfigured(): void
    {
        $stage = new Stage(singleMembership: true);
        $acme = $stage->acme();
        $sales = $stage->units->create($acme->id, Stage::member('ann'), 'Sales');
        $support = $stage->units->create($acme->id, Stage::member('ann'), 'Support');
        $stage->units->addMember($sales->id, Stage::member('ann'), Stage::member('cam'), ['member']);

        try {
            $stage->units->addMember($support->id, Stage::member('ann'), Stage::member('cam'), ['member']);
            self::fail('A member joined a second unit.');
        } catch (AlreadyMember $alreadyMember) {
            self::assertSame('Already a member of another unit of the organization "'.$acme->id->value.'", and a member belongs to one unit at most.', $alreadyMember->getMessage());
        }

        $other = $stage->organizations->create('Other', Stage::member('ann'));
        $stage->members->add($other->id, Stage::member('ann'), Stage::member('cam'), ['member']);
        $elsewhere = $stage->units->create($other->id, Stage::member('ann'), 'Elsewhere');
        $stage->units->addMember($elsewhere->id, Stage::member('ann'), Stage::member('cam'), ['member']);

        $stage->units->removeMember($sales->id, Stage::member('ann'), Stage::member('cam'));
        self::assertSame(['member'], $stage->units->addMember($support->id, Stage::member('ann'), Stage::member('cam'), ['member'])->roles->names);
    }

    public function testAManagerGrantsAndTouchesOnlyRolesTheyHold(): void
    {
        $this->stage->units->addMember($this->east->id, Stage::member('ann'), Stage::member('dan'), ['instructor']);

        foreach ([
            fn (): UnitMembership => $this->stage->units->addMember($this->east->id, Stage::member('cam'), Stage::member('bob'), ['instructor']),
            fn (): UnitMembership => $this->stage->units->changeRoles($this->east->id, Stage::member('cam'), Stage::member('dan'), ['member']),
            fn () => $this->stage->units->removeMember($this->east->id, Stage::member('cam'), Stage::member('dan')),
        ] as $change) {
            try {
                $change();
                self::fail('A manager granted wider roles.');
            } catch (PermissionDenied $permissionDenied) {
                self::assertSame('A member grants, changes or removes only roles whose permissions they hold themselves.', $permissionDenied->getMessage());
            }
        }

        $this->stage->units->changeRoles($this->sales->id, Stage::member('bob'), Stage::member('cam'), ['manager', 'instructor']);
        self::assertSame(['instructor'], $this->stage->units->addMember($this->east->id, Stage::member('cam'), Stage::member('bob'), ['instructor'])->roles->names);
        self::assertSame(['member'], $this->stage->units->changeRoles($this->east->id, Stage::member('cam'), Stage::member('dan'), ['member'])->roles->names);
        self::assertSame('The permission "unit.manage" is required.', $this->denied(fn (): UnitMembership => $this->stage->units->addMember($this->east->id, Stage::member('dan'), Stage::member('dan'), ['member'])));
    }

    public function testAMemberLeavingTheOrganizationLeavesItsUnits(): void
    {
        $this->stage->members->remove($this->acme->id, Stage::member('ann'), Stage::member('cam'));

        self::assertSame([], $this->stage->tree->membershipsOf($this->acme->id, Stage::member('cam')));
        self::assertEquals([new UnitMemberRemoved($this->acme->id->value, $this->sales->id->value, 'cam', ['manager'])], $this->stage->events->of(UnitMemberRemoved::class));
    }

    public function testAnErasedMemberLeavesTheUnits(): void
    {
        $this->stage->erasure->erase(Stage::member('cam'));

        self::assertSame([], $this->stage->unitStore->unitMembershipsOf(Stage::member('cam')));
        self::assertEquals([new UnitMemberRemoved($this->acme->id->value, $this->sales->id->value, 'cam', ['manager'])], $this->stage->events->of(UnitMemberRemoved::class));
    }

    public function testAChangeThatThrowsLeavesTheStoreAsItWas(): void
    {
        self::assertInstanceOf(UnitStore::class, $this->stage->store);

        try {
            $this->stage->units->move($this->sales->id, Stage::member('ann'), $this->east->id);
        } catch (UnitCycle) {
        }

        self::assertSame([$this->sales->id->value, $this->east->id->value], array_map(static fn (Unit $unit): string => $unit->id->value, $this->stage->tree->units($this->acme->id)));
        self::assertSame([], $this->stage->events->events);
    }

    /**
     * @param callable(): mixed $change
     */
    private function denied(callable $change): string
    {
        try {
            $change();
        } catch (PermissionDenied $permissionDenied) {
            return $permissionDenied->getMessage();
        }

        self::fail('The change was allowed.');
    }
}
