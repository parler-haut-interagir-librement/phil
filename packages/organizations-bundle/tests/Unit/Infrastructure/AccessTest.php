<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Infrastructure;

use Phil\AccessBundle\Application\AccessModel;
use Phil\AccessBundle\Application\Contract\ModelContributor;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\OrganizationsBundle\Application\RoleCatalog;
use Phil\OrganizationsBundle\Domain\Event\MemberJoined;
use Phil\OrganizationsBundle\Domain\Event\MemberLeft;
use Phil\OrganizationsBundle\Domain\Event\MemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Event\TeamCreated;
use Phil\OrganizationsBundle\Domain\Event\TeamDeleted;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberAdded;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRemoved;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Event\UnitCreated;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberAdded;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberRemoved;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Event\UnitMoved;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\Status\LeaveReason;
use Phil\OrganizationsBundle\Infrastructure\Access\MembershipTuples;
use Phil\OrganizationsBundle\Infrastructure\Access\OrganizationRelations;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\FixedPartition;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\TypeDefinition;
use Phil\ResourcePermissions\RelationChecker;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(OrganizationRelations::class)]
#[CoversClass(MembershipTuples::class)]
final class AccessTest extends TestCase
{
    private InMemoryTupleStore $store;

    private MembershipTuples $tuples;

    private Model $model;

    protected function setUp(): void
    {
        $relations = new OrganizationRelations(RoleCatalog::organization(['billing' => ['billing.read']]), RoleCatalog::team(['reviewer' => []]));
        $this->model = AccessModel::create(['page'], SharingLevels::of(['edit', 'view']), [$relations]);
        $this->store = new InMemoryTupleStore(new MockClock());
        $this->tuples = new MembershipTuples(new RelationWriter($this->model, $this->store), $relations);
    }

    public function testTheKindsOfTheOrganizations(): void
    {
        $types = new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team())->contribute(['member' => TypeDefinition::of('member')]);

        self::assertSame(['member', 'organization', 'team'], array_keys($types));
        self::assertTrue($types['organization']->has('owner'));
        self::assertTrue($types['organization']->has('admin'));
        self::assertTrue($types['organization']->has('member'));
        self::assertSame('[member]', $types['organization']->rewrite('owner')->toString());
        self::assertSame('[organization]', $types['team']->rewrite('organization')->toString());
        self::assertTrue($types['team']->has('maintainer'));
        self::assertTrue($this->model->accepts('organization', 'billing', Subject::of(ResourceReference::of('member', 'ann'))));
        self::assertTrue($this->model->accepts('team', 'reviewer', Subject::of(ResourceReference::of('member', 'ann'))));
    }

    public function testKindsNamedOtherwise(): void
    {
        $relations = new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team(), 'guild', 'squad');
        $types = $relations->contribute([]);

        self::assertSame(['guild', 'squad'], array_keys($types));
        self::assertSame('[guild]', $types['squad']->rewrite('organization')->toString());
        self::assertSame('guild', $relations->organizationKind);
        self::assertSame('squad', $relations->teamKind);
    }

    public function testAKindAlreadyInTheModelIsRefused(): void
    {
        foreach (['organization', 'team'] as $kind) {
            try {
                new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team())->contribute([$kind => TypeDefinition::of($kind)]);
                self::fail('A kind was declared twice.');
            } catch (InvalidOrganization $invalidOrganization) {
                self::assertSame(\sprintf('The kind "%s" is already in the access model: name the kinds of the organizations otherwise.', $kind), $invalidOrganization->getMessage());
            }
        }

        $this->expectException(InvalidOrganization::class);
        $this->expectExceptionMessageIsOrContains('A team role cannot be named "organization", the relation of a team to its organization.');

        new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team(['organization' => []]))->contribute([]);
    }

    public function testMembershipsBecomeTuples(): void
    {
        $this->tuples->onMemberJoined(new MemberJoined('o1', 'ann', ['admin', 'owner'], JoinMethod::Created));
        $this->tuples->onMemberJoined(new MemberJoined('o1', 'bob', ['member'], JoinMethod::Added, 'ann'));
        self::assertSame(['ann'], $this->holders('organization', 'o1', 'owner'));
        self::assertSame(['ann'], $this->holders('organization', 'o1', 'admin'));
        self::assertSame(['bob'], $this->holders('organization', 'o1', 'member'));

        $this->tuples->onMemberRolesChanged(new MemberRolesChanged('o1', 'bob', ['member'], ['billing', 'member'], 'ann'));
        $this->tuples->onMemberRolesChanged(new MemberRolesChanged('o1', 'ann', ['admin', 'owner'], ['owner'], 'ann'));
        self::assertSame(['bob'], $this->holders('organization', 'o1', 'billing'));
        self::assertSame(['bob'], $this->holders('organization', 'o1', 'member'));
        self::assertSame([], $this->holders('organization', 'o1', 'admin'));
        self::assertSame(['ann'], $this->holders('organization', 'o1', 'owner'));

        $this->tuples->onMemberLeft(new MemberLeft('o1', 'bob', ['billing', 'member'], LeaveReason::Left));
        self::assertSame([], $this->holders('organization', 'o1', 'member'));
        self::assertSame([], $this->holders('organization', 'o1', 'billing'));
    }

    public function testTeamsBecomeTuples(): void
    {
        $this->tuples->onTeamCreated(new TeamCreated('o1', 't1', 'ann'));
        $this->tuples->onTeamMemberAdded(new TeamMemberAdded('o1', 't1', 'cam', ['member'], 'ann'));
        $this->tuples->onTeamMemberAdded(new TeamMemberAdded('o1', 't1', 'dan', ['maintainer']));

        self::assertEquals([new RelationTuple(ResourceReference::of('team', 't1'), 'organization', Subject::of(ResourceReference::of('organization', 'o1')))], $this->store->subjects(ResourceReference::of('team', 't1'), 'organization', 10));
        self::assertSame(['cam'], $this->holders('team', 't1', 'member'));

        $this->tuples->onTeamMemberRolesChanged(new TeamMemberRolesChanged('o1', 't1', 'cam', ['member'], ['reviewer'], 'dan'));
        self::assertSame([], $this->holders('team', 't1', 'member'));
        self::assertSame(['cam'], $this->holders('team', 't1', 'reviewer'));

        $this->tuples->onTeamMemberRemoved(new TeamMemberRemoved('o1', 't1', 'cam', ['reviewer']));
        self::assertSame([], $this->holders('team', 't1', 'reviewer'));
        self::assertSame(['dan'], $this->holders('team', 't1', 'maintainer'));

        $this->tuples->onTeamDeleted(new TeamDeleted('o1', 't1', 'ann'));
        self::assertSame([], $this->holders('team', 't1', 'maintainer'));
        self::assertSame([], $this->store->subjects(ResourceReference::of('team', 't1'), 'organization', 10));
    }

    public function testAnApplicationRelationBuildsOnTheRoles(): void
    {
        $model = AccessModel::create(['page'], SharingLevels::of(['view']), [
            new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team()),
            new class implements ModelContributor {
                public function contribute(array $types): array
                {
                    $types['page'] = $types['page']->with([
                        'organization' => Rewrite::direct('organization'),
                        'editor' => Rewrite::fromRelated('organization', 'admin'),
                    ]);

                    return $types;
                }
            },
        ]);
        $store = new InMemoryTupleStore(new MockClock(), new FixedPartition('tenant-a'));
        $writer = new RelationWriter($model, $store);
        $tuples = new MembershipTuples($writer, new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team()));
        $tuples->onMemberJoined(new MemberJoined('o1', 'bob', ['admin'], JoinMethod::Added, 'ann'));

        $writer->grant(ResourceReference::of('page', 'p1'), 'organization', ResourceReference::of('organization', 'o1'));
        $checker = new RelationChecker($model, $store, new Budget());

        self::assertTrue($checker->check(ResourceReference::of('member', 'bob'), 'editor', ResourceReference::of('page', 'p1')));
        self::assertFalse($checker->check(ResourceReference::of('member', 'cam'), 'editor', ResourceReference::of('page', 'p1')));
        self::assertFalse(new RelationChecker($model, new InMemoryTupleStore(new MockClock(), new FixedPartition('tenant-b')), new Budget())->check(ResourceReference::of('member', 'bob'), 'editor', ResourceReference::of('page', 'p1')));
    }

    public function testTheKindOfTheUnits(): void
    {
        $relations = new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team(), unitRoles: RoleCatalog::unit(['instructor' => ['courses.assign']]));
        $types = $relations->contribute([]);

        self::assertSame(['organization', 'team', 'unit'], array_keys($types));
        self::assertSame('unit', $relations->unitKind);
        self::assertSame('[organization]', $types['unit']->rewrite('organization')->toString());
        self::assertSame('[unit]', $types['unit']->rewrite('parent')->toString());
        self::assertSame('([member] or manager from parent)', $types['unit']->rewrite('manager')->toString());
        self::assertSame('([member] or member from parent)', $types['unit']->rewrite('member')->toString());
        self::assertSame('([member] or instructor from parent)', $types['unit']->rewrite('instructor')->toString());

        $named = new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team(), 'guild', 'squad', RoleCatalog::unit(), 'branch');
        $branch = $named->contribute([])['branch'];
        self::assertSame('branch', $named->unitKind);
        self::assertSame('[guild]', $branch->rewrite('organization')->toString());
        self::assertSame('[branch]', $branch->rewrite('parent')->toString());
    }

    public function testTheUnitsLeftOutOfTheModel(): void
    {
        $withoutRoles = new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team(), unitKind: 'unit');
        self::assertNull($withoutRoles->unitKind);
        self::assertSame(['organization', 'team'], array_keys($withoutRoles->contribute([])));

        $withoutKind = new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team(), unitRoles: RoleCatalog::unit(), unitKind: null);
        self::assertNull($withoutKind->unitKind);
        self::assertSame(['unit', 'organization', 'team'], array_keys($withoutKind->contribute(['unit' => TypeDefinition::of('unit')])));

        $this->tuples->onUnitCreated(new UnitCreated('o1', 'u1', 'u0', 'ann'));
        $this->tuples->onUnitMoved(new UnitMoved('o1', 'u1', 'u0', 'u2', 'ann'));
        $this->tuples->onUnitMemberAdded(new UnitMemberAdded('o1', 'u1', 'bob', ['member'], 'ann'));
        $this->tuples->onUnitMemberRolesChanged(new UnitMemberRolesChanged('o1', 'u1', 'bob', ['member'], ['manager'], 'ann'));
        $this->tuples->onUnitMemberRemoved(new UnitMemberRemoved('o1', 'u1', 'bob', ['manager']));
        self::assertSame(0, $this->store->forget(ResourceReference::of('unit', 'u1')));
    }

    public function testAUnitKindOrRoleThatCollidesIsRefused(): void
    {
        try {
            new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team(), unitRoles: RoleCatalog::unit())->contribute(['unit' => TypeDefinition::of('unit')]);
            self::fail('The kind of the units was declared twice.');
        } catch (InvalidOrganization $invalidOrganization) {
            self::assertSame('The kind "unit" is already in the access model: name the kinds of the organizations otherwise.', $invalidOrganization->getMessage());
        }

        foreach (['organization', 'parent'] as $role) {
            try {
                new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team(), unitRoles: RoleCatalog::unit([$role => []]))->contribute([]);
                self::fail('A unit role was named after a relation.');
            } catch (InvalidOrganization $invalidOrganization) {
                self::assertSame(\sprintf('A unit role cannot be named "%s", a relation of a unit in the access model.', $role), $invalidOrganization->getMessage());
            }
        }
    }

    public function testUnitsBecomeTuplesAndTheirRolesHoldBelow(): void
    {
        $relations = new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team(), unitRoles: RoleCatalog::unit(['instructor' => []]));
        $model = AccessModel::create(['page'], SharingLevels::of(['view']), [$relations]);
        $store = new InMemoryTupleStore(new MockClock());
        $tuples = new MembershipTuples(new RelationWriter($model, $store), $relations);
        $checker = new RelationChecker($model, $store, new Budget());
        $member = static fn (string $id): ResourceReference => ResourceReference::of('member', $id);
        $unit = static fn (string $id): ResourceReference => ResourceReference::of('unit', $id);

        $tuples->onUnitCreated(new UnitCreated('o1', 'sales', null, 'ann'));
        $tuples->onUnitCreated(new UnitCreated('o1', 'east', 'sales', 'ann'));
        $tuples->onUnitCreated(new UnitCreated('o1', 'boston', 'east', 'ann'));
        $tuples->onUnitCreated(new UnitCreated('o1', 'support', null, 'ann'));
        self::assertEquals([new RelationTuple($unit('east'), 'organization', Subject::of(ResourceReference::of('organization', 'o1')))], $store->subjects($unit('east'), 'organization', 10));
        self::assertEquals([new RelationTuple($unit('east'), 'parent', Subject::of($unit('sales')))], $store->subjects($unit('east'), 'parent', 10));
        self::assertSame([], $store->subjects($unit('sales'), 'parent', 10));

        $tuples->onUnitMemberAdded(new UnitMemberAdded('o1', 'sales', 'bob', ['manager'], 'ann'));
        $tuples->onUnitMemberAdded(new UnitMemberAdded('o1', 'boston', 'cam', ['member'], 'bob'));
        self::assertTrue($checker->check($member('bob'), 'manager', $unit('sales')));
        self::assertTrue($checker->check($member('bob'), 'manager', $unit('boston')));
        self::assertFalse($checker->check($member('bob'), 'manager', $unit('support')));
        self::assertTrue($checker->check($member('cam'), 'member', $unit('boston')));
        self::assertFalse($checker->check($member('cam'), 'member', $unit('east')));

        $tuples->onUnitMoved(new UnitMoved('o1', 'east', 'sales', 'support', 'ann'));
        self::assertEquals([new RelationTuple($unit('east'), 'parent', Subject::of($unit('support')))], $store->subjects($unit('east'), 'parent', 10));
        self::assertFalse($checker->check($member('bob'), 'manager', $unit('boston')));

        $tuples->onUnitMoved(new UnitMoved('o1', 'east', 'support', null, 'ann'));
        self::assertSame([], $store->subjects($unit('east'), 'parent', 10));
        $tuples->onUnitMoved(new UnitMoved('o1', 'east', null, 'sales', 'ann'));
        self::assertTrue($checker->check($member('bob'), 'manager', $unit('boston')));

        $tuples->onUnitMemberRolesChanged(new UnitMemberRolesChanged('o1', 'sales', 'bob', ['manager'], ['instructor', 'manager'], 'ann'));
        self::assertTrue($checker->check($member('bob'), 'instructor', $unit('boston')));
        $tuples->onUnitMemberRolesChanged(new UnitMemberRolesChanged('o1', 'sales', 'bob', ['instructor', 'manager'], ['instructor'], 'ann'));
        self::assertFalse($checker->check($member('bob'), 'manager', $unit('boston')));

        $tuples->onUnitMemberRemoved(new UnitMemberRemoved('o1', 'sales', 'bob', ['instructor']));
        self::assertFalse($checker->check($member('bob'), 'instructor', $unit('sales')));
        self::assertTrue($checker->check($member('cam'), 'member', $unit('boston')));
    }

    public function testARoleHoldsSixteenLevelsDownWithinTheDefaultBudget(): void
    {
        $relations = new OrganizationRelations(RoleCatalog::organization(), RoleCatalog::team(), unitRoles: RoleCatalog::unit());
        $model = AccessModel::create(['page'], SharingLevels::of(['view']), [$relations]);
        $store = new InMemoryTupleStore(new MockClock());
        $tuples = new MembershipTuples(new RelationWriter($model, $store), $relations);

        for ($level = 1; $level <= 16; ++$level) {
            $tuples->onUnitCreated(new UnitCreated('o1', 'u'.$level, 1 === $level ? null : 'u'.($level - 1), 'ann'));
        }

        $tuples->onUnitMemberAdded(new UnitMemberAdded('o1', 'u1', 'bob', ['manager'], 'ann'));

        self::assertTrue(new RelationChecker($model, $store, new Budget())->check(ResourceReference::of('member', 'bob'), 'manager', ResourceReference::of('unit', 'u16')));
    }

    /**
     * @return list<string>
     */
    private function holders(string $kind, string $id, string $relation): array
    {
        return array_map(static fn (RelationTuple $tuple): string => $tuple->subject->reference->id, $this->store->subjects(ResourceReference::of($kind, $id), $relation, 10));
    }
}
