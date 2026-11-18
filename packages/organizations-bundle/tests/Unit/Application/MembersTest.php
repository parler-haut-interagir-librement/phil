<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Application;

use Phil\OrganizationsBundle\Application\Exception\AlreadyMember;
use Phil\OrganizationsBundle\Application\Exception\LastOwner;
use Phil\OrganizationsBundle\Application\Exception\NotAMember;
use Phil\OrganizationsBundle\Application\Exception\OrganizationArchived;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\Exception\UnknownRole;
use Phil\OrganizationsBundle\Application\Members;
use Phil\OrganizationsBundle\Domain\Event\MemberJoined;
use Phil\OrganizationsBundle\Domain\Event\MemberLeft;
use Phil\OrganizationsBundle\Domain\Event\MemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRemoved;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\Status\LeaveReason;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Members::class)]
#[CoversClass(AlreadyMember::class)]
#[CoversClass(LastOwner::class)]
#[CoversClass(NotAMember::class)]
#[CoversClass(PermissionDenied::class)]
final class MembersTest extends TestCase
{
    private Stage $stage;

    private Organization $acme;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->acme = $this->stage->acme();
    }

    public function testAdd(): void
    {
        $membership = $this->stage->members->add($this->acme->id, Stage::member('bob'), Stage::member('dan'), ['member', 'admin']);

        self::assertSame(['admin', 'member'], $membership->roles->names);
        self::assertSame(JoinMethod::Added, $membership->method);
        self::assertSame($this->stage->clock->now()->getTimestamp(), $membership->joinedAt->getTimestamp());
        self::assertEquals($membership, $this->stage->members->membership($this->acme->id, Stage::member('dan')));
        self::assertEquals([new MemberJoined($this->acme->id->value, 'dan', ['admin', 'member'], JoinMethod::Added, 'bob')], $this->stage->events->events);
        self::assertSame(['ann', 'bob', 'cam', 'dan'], array_map(static fn (Membership $m): string => $m->memberId->value, $this->stage->members->members($this->acme->id)));
        self::assertSame(['cam', 'dan'], array_map(static fn (Membership $m): string => $m->memberId->value, $this->stage->members->members($this->acme->id, 10, 'bob')));
        self::assertSame(['ann'], array_map(static fn (Membership $m): string => $m->memberId->value, $this->stage->members->members($this->acme->id, 1)));
    }

    public function testAddTwice(): void
    {
        try {
            $this->stage->members->add($this->acme->id, Stage::member('ann'), Stage::member('cam'), ['admin']);
            self::fail('A member was added twice.');
        } catch (AlreadyMember $alreadyMember) {
            self::assertSame(\sprintf('Already a member of the organization "%s".', $this->acme->id->value), $alreadyMember->getMessage());
        }

        self::assertSame(['member'], $this->stage->members->membership($this->acme->id, Stage::member('cam'))?->roles->names);
    }

    public function testAnUndeclaredRoleIsRefused(): void
    {
        $this->expectException(UnknownRole::class);

        $this->stage->members->add($this->acme->id, Stage::member('ann'), Stage::member('dan'), ['ghost']);
    }

    public function testAnAdminGrantsNoWiderRoleThanTheirOwn(): void
    {
        foreach ([['owner'], ['admin', 'owner']] as $roles) {
            try {
                $this->stage->members->add($this->acme->id, Stage::member('bob'), Stage::member('dan'), $roles);
                self::fail('An admin named an owner.');
            } catch (PermissionDenied $permissionDenied) {
                self::assertSame('A member grants, changes or removes only roles whose permissions they hold themselves.', $permissionDenied->getMessage());
            }
        }

        self::assertNull($this->stage->members->membership($this->acme->id, Stage::member('dan')));
    }

    public function testAMemberAddsNobody(): void
    {
        $this->expectException(PermissionDenied::class);
        $this->expectExceptionMessageIsOrContains('The permission "members.manage" is required.');

        $this->stage->members->add($this->acme->id, Stage::member('cam'), Stage::member('dan'), ['member']);
    }

    public function testNobodyIsAddedToAnArchivedOrganization(): void
    {
        $this->stage->organizations->archive($this->acme->id, Stage::member('ann'));

        $this->expectException(OrganizationArchived::class);

        $this->stage->members->add($this->acme->id, Stage::member('ann'), Stage::member('dan'), ['member']);
    }

    public function testChangeRoles(): void
    {
        $membership = $this->stage->members->changeRoles($this->acme->id, Stage::member('bob'), Stage::member('cam'), ['admin']);

        self::assertSame(['admin'], $membership->roles->names);
        self::assertSame(['admin'], $this->stage->members->membership($this->acme->id, Stage::member('cam'))?->roles->names);
        self::assertEquals([new MemberRolesChanged($this->acme->id->value, 'cam', ['member'], ['admin'], 'bob')], $this->stage->events->events);

        $this->stage->events->clear();
        $this->stage->members->changeRoles($this->acme->id, Stage::member('bob'), Stage::member('cam'), ['admin']);
        self::assertSame([], $this->stage->events->events);
    }

    public function testAnAdminChangesNeitherAnOwnerNorToOwner(): void
    {
        foreach ([['ann', ['admin']], ['cam', ['owner']]] as [$member, $roles]) {
            try {
                $this->stage->members->changeRoles($this->acme->id, Stage::member('bob'), Stage::member($member), $roles);
                self::fail('An admin went past their own roles.');
            } catch (PermissionDenied) {
            }
        }

        self::assertSame(['owner'], $this->stage->members->membership($this->acme->id, Stage::member('ann'))?->roles->names);
        self::assertSame(['member'], $this->stage->members->membership($this->acme->id, Stage::member('cam'))?->roles->names);
    }

    public function testTheLastOwnerKeepsTheOwnerRole(): void
    {
        try {
            $this->stage->members->changeRoles($this->acme->id, Stage::member('ann'), Stage::member('ann'), ['admin']);
            self::fail('The last owner was demoted.');
        } catch (LastOwner $lastOwner) {
            self::assertSame(\sprintf('The organization "%s" keeps at least one owner: name another owner first.', $this->acme->id->value), $lastOwner->getMessage());
        }

        self::assertSame(['admin', 'owner'], $this->stage->members->changeRoles($this->acme->id, Stage::member('ann'), Stage::member('ann'), ['owner', 'admin'])->roles->names);
        $this->stage->members->changeRoles($this->acme->id, Stage::member('ann'), Stage::member('bob'), ['owner']);
        self::assertSame(['admin'], $this->stage->members->changeRoles($this->acme->id, Stage::member('bob'), Stage::member('ann'), ['admin'])->roles->names);
    }

    public function testChangeTheRolesOfAStranger(): void
    {
        $this->expectException(NotAMember::class);

        $this->stage->members->changeRoles($this->acme->id, Stage::member('ann'), Stage::member('dan'), ['admin']);
    }

    public function testRemove(): void
    {
        $team = $this->stage->teams->create($this->acme->id, Stage::member('ann'), 'Design');
        $this->stage->teams->addMember($team->id, Stage::member('ann'), Stage::member('cam'), ['member']);
        $this->stage->events->clear();

        $this->stage->members->remove($this->acme->id, Stage::member('bob'), Stage::member('cam'));

        self::assertNull($this->stage->members->membership($this->acme->id, Stage::member('cam')));
        self::assertNull($this->stage->teams->membership($team->id, Stage::member('cam')));
        self::assertEquals([
            new TeamMemberRemoved($this->acme->id->value, $team->id->value, 'cam', ['member']),
            new MemberLeft($this->acme->id->value, 'cam', ['member'], LeaveReason::Removed, 'bob'),
        ], $this->stage->events->events);
    }

    public function testAnAdminRemovesNoOwnerAndAMemberNobody(): void
    {
        try {
            $this->stage->members->remove($this->acme->id, Stage::member('bob'), Stage::member('ann'));
            self::fail('An admin removed an owner.');
        } catch (PermissionDenied) {
        }

        try {
            $this->stage->members->remove($this->acme->id, Stage::member('cam'), Stage::member('bob'));
            self::fail('A member removed an admin.');
        } catch (PermissionDenied) {
        }

        self::assertSame(3, $this->stage->store->countMembers($this->acme->id));
        self::assertSame([], $this->stage->events->events);
    }

    public function testTheLastOwnerIsNeverRemovedNorLeaves(): void
    {
        $this->stage->members->add($this->acme->id, Stage::member('ann'), Stage::member('eve'), ['owner']);

        $this->stage->members->remove($this->acme->id, Stage::member('ann'), Stage::member('eve'));
        self::assertNull($this->stage->members->membership($this->acme->id, Stage::member('eve')));

        foreach ([fn () => $this->stage->members->leave($this->acme->id, Stage::member('ann')), fn () => $this->stage->members->remove($this->acme->id, Stage::member('ann'), Stage::member('ann'))] as $change) {
            try {
                $change();
                self::fail('The last owner left.');
            } catch (LastOwner) {
            }
        }

        self::assertTrue($this->stage->access->hasRole($this->acme->id, Stage::member('ann'), 'owner'));
    }

    public function testLeave(): void
    {
        $this->stage->members->remove($this->acme->id, Stage::member('cam'), Stage::member('cam'));

        self::assertNull($this->stage->members->membership($this->acme->id, Stage::member('cam')));
        self::assertEquals([new MemberLeft($this->acme->id->value, 'cam', ['member'], LeaveReason::Left)], $this->stage->events->events);

        $this->stage->organizations->archive($this->acme->id, Stage::member('ann'));
        $this->stage->members->leave($this->acme->id, Stage::member('bob'));
        self::assertNull($this->stage->members->membership($this->acme->id, Stage::member('bob')));

        $this->expectException(NotAMember::class);

        $this->stage->members->leave($this->acme->id, Stage::member('bob'));
    }

    public function testASecondOwnerLeaves(): void
    {
        $this->stage->members->changeRoles($this->acme->id, Stage::member('ann'), Stage::member('bob'), ['owner']);
        $this->stage->members->leave($this->acme->id, Stage::member('ann'));

        self::assertSame(1, $this->stage->ownership->owners($this->acme->id));
        self::assertTrue($this->stage->access->hasRole($this->acme->id, Stage::member('bob'), 'owner'));
    }
}
