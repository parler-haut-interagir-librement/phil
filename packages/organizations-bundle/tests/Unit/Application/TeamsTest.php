<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Application;

use Phil\OrganizationsBundle\Application\Exception\AlreadyMember;
use Phil\OrganizationsBundle\Application\Exception\NotAMember;
use Phil\OrganizationsBundle\Application\Exception\OrganizationArchived;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\Exception\SlugTaken;
use Phil\OrganizationsBundle\Application\Exception\UnknownRole;
use Phil\OrganizationsBundle\Application\Exception\UnknownTeam;
use Phil\OrganizationsBundle\Application\Teams;
use Phil\OrganizationsBundle\Domain\Event\TeamCreated;
use Phil\OrganizationsBundle\Domain\Event\TeamDeleted;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberAdded;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRemoved;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Team;
use Phil\OrganizationsBundle\Domain\Model\TeamMembership;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Teams::class)]
#[CoversClass(UnknownTeam::class)]
#[CoversClass(AlreadyMember::class)]
#[CoversClass(NotAMember::class)]
final class TeamsTest extends TestCase
{
    private Stage $stage;

    private Organization $acme;

    private Team $design;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->acme = $this->stage->acme();
        $this->design = $this->stage->teams->create($this->acme->id, Stage::member('bob'), 'Design');
        $this->stage->members->add($this->acme->id, Stage::member('ann'), Stage::member('dan'), ['member']);
        $this->stage->teams->addMember($this->design->id, Stage::member('bob'), Stage::member('cam'), ['maintainer']);
        $this->stage->events->clear();
    }

    public function testCreate(): void
    {
        $team = $this->stage->teams->create($this->acme->id, Stage::member('ann'), ' Écriture ', 'writers');

        self::assertSame('Écriture', $team->name->value);
        self::assertSame('writers', $team->slug->value);
        self::assertSame('design', $this->design->slug->value);
        self::assertEquals($team, $this->stage->teams->get($team->id));
        self::assertSame(['design', 'writers'], array_map(static fn (Team $t): string => $t->slug->value, $this->stage->teams->teams($this->acme->id)));
        self::assertEquals([new TeamCreated($this->acme->id->value, $team->id->value, 'ann')], $this->stage->events->events);
    }

    public function testCreateWithATakenSlug(): void
    {
        try {
            $this->stage->teams->create($this->acme->id, Stage::member('ann'), 'Design');
            self::fail('A taken slug was taken.');
        } catch (SlugTaken $slugTaken) {
            self::assertSame('The slug "design" is taken.', $slugTaken->getMessage());
        }

        $other = $this->stage->organizations->create('Other', Stage::member('ann'));
        self::assertSame('design', $this->stage->teams->create($other->id, Stage::member('ann'), 'Design')->slug->value);
    }

    public function testOnlyThoseWhoManageTeamsCreateAndDelete(): void
    {
        foreach ([fn (): Team => $this->stage->teams->create($this->acme->id, Stage::member('cam'), 'Mine'), fn () => $this->stage->teams->delete($this->design->id, Stage::member('cam'))] as $change) {
            try {
                $change();
                self::fail('A member managed teams.');
            } catch (PermissionDenied $permissionDenied) {
                self::assertSame('The permission "teams.manage" is required.', $permissionDenied->getMessage());
            }
        }
    }

    public function testRename(): void
    {
        $renamed = $this->stage->teams->rename($this->design->id, Stage::member('cam'), 'Product design');
        self::assertSame('design', $renamed->slug->value);
        self::assertSame('Product design', $this->stage->teams->get($this->design->id)->name->value);
        self::assertSame('product', $this->stage->teams->rename($this->design->id, Stage::member('bob'), 'Product', 'product')->slug->value);
        self::assertSame('product', $this->stage->teams->rename($this->design->id, Stage::member('bob'), 'Product!', 'product')->slug->value);

        $this->stage->teams->create($this->acme->id, Stage::member('ann'), 'Writers');

        $this->expectException(SlugTaken::class);

        $this->stage->teams->rename($this->design->id, Stage::member('bob'), 'Writers', 'writers');
    }

    public function testDelete(): void
    {
        $this->stage->teams->delete($this->design->id, Stage::member('bob'));

        self::assertSame([], $this->stage->teams->teams($this->acme->id));
        self::assertSame([], $this->stage->teams->members($this->design->id));
        self::assertEquals([
            new TeamMemberRemoved($this->acme->id->value, $this->design->id->value, 'cam', ['maintainer'], 'bob'),
            new TeamDeleted($this->acme->id->value, $this->design->id->value, 'bob'),
        ], $this->stage->events->events);

        try {
            $this->stage->teams->get($this->design->id);
            self::fail('A deleted team was found.');
        } catch (UnknownTeam $unknownTeam) {
            self::assertSame(\sprintf('No team "%s".', $this->design->id->value), $unknownTeam->getMessage());
        }
    }

    public function testAMaintainerManagesTheirTeam(): void
    {
        $membership = $this->stage->teams->addMember($this->design->id, Stage::member('cam'), Stage::member('dan'), ['member']);

        self::assertSame(['member'], $membership->roles->names);
        self::assertSame($this->stage->clock->now()->getTimestamp(), $membership->joinedAt->getTimestamp());
        self::assertEquals($membership, $this->stage->teams->membership($this->design->id, Stage::member('dan')));
        self::assertSame(['cam', 'dan'], array_map(static fn (TeamMembership $m): string => $m->memberId->value, $this->stage->teams->members($this->design->id)));
        self::assertSame(['team.manage'], $this->stage->teams->permissions($this->design->id, Stage::member('cam')));
        self::assertSame([], $this->stage->teams->permissions($this->design->id, Stage::member('dan')));
        self::assertSame([], $this->stage->teams->permissions($this->design->id, Stage::member('ann')));

        self::assertSame(['maintainer'], $this->stage->teams->changeRoles($this->design->id, Stage::member('cam'), Stage::member('dan'), ['maintainer'])->roles->names);
        self::assertSame(['maintainer'], $this->stage->teams->membership($this->design->id, Stage::member('dan'))?->roles->names);
        $this->stage->teams->changeRoles($this->design->id, Stage::member('cam'), Stage::member('dan'), ['maintainer']);
        $this->stage->teams->removeMember($this->design->id, Stage::member('cam'), Stage::member('dan'));
        self::assertNull($this->stage->teams->membership($this->design->id, Stage::member('dan')));

        self::assertEquals([
            new TeamMemberAdded($this->acme->id->value, $this->design->id->value, 'dan', ['member'], 'cam'),
            new TeamMemberRolesChanged($this->acme->id->value, $this->design->id->value, 'dan', ['member'], ['maintainer'], 'cam'),
            new TeamMemberRemoved($this->acme->id->value, $this->design->id->value, 'dan', ['maintainer'], 'cam'),
        ], $this->stage->events->events);
    }

    public function testAMaintainerGrantsNoWiderTeamRole(): void
    {
        $this->stage->teams->addMember($this->design->id, Stage::member('bob'), Stage::member('dan'), ['reviewer']);

        foreach ([
            fn (): TeamMembership => $this->stage->teams->addMember($this->design->id, Stage::member('cam'), Stage::member('ann'), ['reviewer']),
            fn (): TeamMembership => $this->stage->teams->changeRoles($this->design->id, Stage::member('cam'), Stage::member('dan'), ['member']),
            fn () => $this->stage->teams->removeMember($this->design->id, Stage::member('cam'), Stage::member('dan')),
        ] as $change) {
            try {
                $change();
                self::fail('A maintainer went past their own roles.');
            } catch (PermissionDenied $permissionDenied) {
                self::assertSame('A member grants, changes or removes only roles whose permissions they hold themselves.', $permissionDenied->getMessage());
            }
        }

        self::assertSame(['reviewer'], $this->stage->teams->membership($this->design->id, Stage::member('dan'))?->roles->names);
    }

    public function testAMemberOfTheTeamManagesNothing(): void
    {
        $this->stage->teams->addMember($this->design->id, Stage::member('bob'), Stage::member('dan'), ['member']);

        foreach ([
            fn (): TeamMembership => $this->stage->teams->addMember($this->design->id, Stage::member('dan'), Stage::member('ann'), ['member']),
            fn (): Team => $this->stage->teams->rename($this->design->id, Stage::member('dan'), 'Mine'),
            fn () => $this->stage->teams->removeMember($this->design->id, Stage::member('dan'), Stage::member('cam')),
        ] as $change) {
            try {
                $change();
            } catch (PermissionDenied $permissionDenied) {
                self::assertSame('The permission "team.manage" is required.', $permissionDenied->getMessage());

                continue;
            }

            self::fail('A change went through.');
        }
    }

    public function testAStrangerManagesNothing(): void
    {
        $this->expectException(NotAMember::class);

        $this->stage->teams->rename($this->design->id, Stage::member('zed'), 'Mine');
    }

    public function testOnlyMembersOfTheOrganizationJoinItsTeams(): void
    {
        try {
            $this->stage->teams->addMember($this->design->id, Stage::member('bob'), Stage::member('zed'), ['member']);
            self::fail('A stranger joined a team.');
        } catch (NotAMember $notaMember) {
            self::assertSame(\sprintf('Not a member of the organization "%s".', $this->acme->id->value), $notaMember->getMessage());
        }

        try {
            $this->stage->teams->addMember($this->design->id, Stage::member('bob'), Stage::member('cam'), ['member']);
            self::fail('A member joined a team twice.');
        } catch (AlreadyMember $alreadyMember) {
            self::assertSame(\sprintf('Already a member of the team "%s".', $this->design->id->value), $alreadyMember->getMessage());
        }

        try {
            $this->stage->teams->addMember($this->design->id, Stage::member('bob'), Stage::member('dan'), ['ghost']);
            self::fail('An undeclared role was granted.');
        } catch (UnknownRole) {
        }

        foreach ([fn (): TeamMembership => $this->stage->teams->changeRoles($this->design->id, Stage::member('bob'), Stage::member('dan'), ['member']), fn () => $this->stage->teams->removeMember($this->design->id, Stage::member('bob'), Stage::member('dan'))] as $change) {
            try {
                $change();
                self::fail('A non-member of the team was changed.');
            } catch (NotAMember $notAMember) {
                self::assertSame(\sprintf('Not a member of the team "%s".', $this->design->id->value), $notAMember->getMessage());
            }
        }
    }

    public function testAMemberLeavesATeamOnTheirOwn(): void
    {
        $this->stage->teams->removeMember($this->design->id, Stage::member('cam'), Stage::member('cam'));

        self::assertNull($this->stage->teams->membership($this->design->id, Stage::member('cam')));
        self::assertEquals([new TeamMemberRemoved($this->acme->id->value, $this->design->id->value, 'cam', ['maintainer'])], $this->stage->events->events);
    }

    public function testTeamsOfAnArchivedOrganizationAreReadOnly(): void
    {
        $this->stage->organizations->archive($this->acme->id, Stage::member('ann'));

        foreach ([
            fn (): Team => $this->stage->teams->create($this->acme->id, Stage::member('ann'), 'Mine'),
            fn (): TeamMembership => $this->stage->teams->addMember($this->design->id, Stage::member('ann'), Stage::member('dan'), ['member']),
            fn (): TeamMembership => $this->stage->teams->addMember($this->design->id, Stage::member('cam'), Stage::member('dan'), ['member']),
        ] as $change) {
            try {
                $change();
                self::fail('An archived organization changed.');
            } catch (OrganizationArchived) {
            }
        }

        self::assertSame(['cam'], array_map(static fn (TeamMembership $m): string => $m->memberId->value, $this->stage->teams->members($this->design->id)));
    }

    public function testAnUnknownTeam(): void
    {
        $this->expectException(UnknownTeam::class);

        $this->stage->teams->addMember(TeamId::fromString('ghost'), Stage::member('ann'), Stage::member('dan'), ['member']);
    }
}
