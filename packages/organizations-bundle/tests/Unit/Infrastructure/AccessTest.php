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

    /**
     * @return list<string>
     */
    private function holders(string $kind, string $id, string $relation): array
    {
        return array_map(static fn (RelationTuple $tuple): string => $tuple->subject->reference->id, $this->store->subjects(ResourceReference::of($kind, $id), $relation, 10));
    }
}
