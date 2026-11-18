<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Application;

use Phil\AccessLinks\TokenHasher;
use Phil\OrganizationsBundle\Application\EventRecorder;
use Phil\OrganizationsBundle\Application\Exception\InvitationNotUsable;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\Exception\SlugTaken;
use Phil\OrganizationsBundle\Application\Invitations;
use Phil\OrganizationsBundle\Application\MemberErasure;
use Phil\OrganizationsBundle\Application\Members;
use Phil\OrganizationsBundle\Application\OrganizationAccess;
use Phil\OrganizationsBundle\Application\Organizations;
use Phil\OrganizationsBundle\Application\OwnershipCheck;
use Phil\OrganizationsBundle\Application\SlugText;
use Phil\OrganizationsBundle\Application\Teams;
use Phil\OrganizationsBundle\Application\UnitOfWork;
use Phil\OrganizationsBundle\Domain\Event\InvitationRevoked;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Infrastructure\Memory\InMemoryOrganizationStore;
use Phil\OrganizationsBundle\Tests\Fixtures\RacingStore;
use Phil\OrganizationsBundle\Tests\Fixtures\RecordingDispatcher;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use Phil\Slug\TransliteratingSlugger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * What the services do at their edges: the size of a page, the locks they take, what they refuse before they write.
 */
#[CoversClass(Invitations::class)]
#[CoversClass(MemberErasure::class)]
#[CoversClass(Members::class)]
#[CoversClass(OrganizationAccess::class)]
#[CoversClass(Organizations::class)]
#[CoversClass(OwnershipCheck::class)]
#[CoversClass(SlugText::class)]
#[CoversClass(Teams::class)]
#[CoversClass(UnitOfWork::class)]
final class BoundariesTest extends TestCase
{
    public function testAPreviewOfAnInvitationWhoseOrganizationIsGone(): void
    {
        $stage = new Stage();
        $elsewhere = new Stage();
        $issued = $elsewhere->invitations->create($elsewhere->acme()->id, Stage::member('ann'), ['member']);
        $stage->store->addInvitation($issued->invitation, new TokenHasher(Stage::KEY)->hash($issued->token));

        try {
            $stage->invitations->preview($issued->token);
            self::fail('An invitation without its organization was previewed.');
        } catch (InvitationNotUsable $invitationNotUsable) {
            self::assertSame(InvitationNotUsable::UNKNOWN, $invitationNotUsable->reason);
        }
    }

    public function testAnInvitationIsRevokedOnceAndTheEventIsRecordedOnce(): void
    {
        $stage = new Stage();
        $acme = $stage->acme();
        $issued = $stage->invitations->create($acme->id, Stage::member('ann'), ['member']);
        $stage->events->clear();

        $stage->invitations->revoke($acme->id, $issued->invitation->id, Stage::member('ann'));
        self::assertEquals([new InvitationRevoked($acme->id->value, $issued->invitation->id->value, 'ann')], $stage->events->events);

        $stage->events->clear();
        $stage->invitations->revoke($acme->id, $issued->invitation->id, Stage::member('ann'));
        self::assertSame([], $stage->events->events);
    }

    public function testErasureLocksEachOrganizationOfTheMember(): void
    {
        $store = new RacingStore();
        $stage = new Stage($store);
        $stage->organizations->create('Alpha', Stage::member('ann'));
        $stage->organizations->create('Bravo', Stage::member('ann'));

        $store->locks = 0;

        $stage->erasure->erase(Stage::member('ann'));

        self::assertSame(2, $store->locks);
    }

    public function testLockingAnOrganizationAsksTheStoreForTheLock(): void
    {
        $store = new RacingStore();
        $stage = new Stage($store);
        $acme = $stage->organizations->create('Alpha', Stage::member('ann'));
        $store->locks = 0;

        $stage->access->lock($acme->id);

        self::assertSame(1, $store->locks);
    }

    public function testAPageIsOneHundredByDefault(): void
    {
        $stage = new Stage();
        $acme = $stage->organizations->create('Org 000', Stage::member('ann'));

        for ($i = 1; $i <= 100; ++$i) {
            $stage->members->add($acme->id, Stage::member('ann'), Stage::member(\sprintf('m%03d', $i)), ['member']);
            $stage->organizations->create(\sprintf('Org %03d', $i), Stage::member('ann'));
        }

        self::assertCount(101, $stage->store->memberships($acme->id, 1000));
        self::assertCount(100, $stage->members->members($acme->id));
        self::assertCount(101, $stage->organizations->list(null, 1000));
        self::assertCount(100, $stage->organizations->list());
        self::assertCount(101, $stage->ownership->belowRecommendation(1000));
        self::assertCount(100, $stage->ownership->belowRecommendation());
    }

    public function testRenamingToATakenSlugWritesNothing(): void
    {
        $store = new RacingStore();
        $stage = new Stage($store);
        $stage->organizations->create('Alpha', Stage::member('ann'), 'alpha');

        $bravo = $stage->organizations->create('Bravo', Stage::member('ann'), 'bravo');
        $saves = $store->organizationSaves;

        try {
            $stage->organizations->rename($bravo->id, Stage::member('ann'), 'Bravo', 'alpha');
            self::fail('A taken slug was taken twice.');
        } catch (SlugTaken $slugTaken) {
            self::assertSame('The slug "alpha" is taken.', $slugTaken->getMessage());
        }

        self::assertSame($saves, $store->organizationSaves);
    }

    public function testRestoringNeedsThePermissionToArchive(): void
    {
        $stage = new Stage();
        $acme = $stage->acme();
        $stage->organizations->archive($acme->id, Stage::member('ann'));

        try {
            $stage->organizations->restore($acme->id, Stage::member('bob'));
            self::fail('An admin restored an organization.');
        } catch (PermissionDenied $permissionDenied) {
            self::assertSame('The permission "organization.archive" is required.', $permissionDenied->getMessage());
        }

        self::assertFalse($stage->organizations->get($acme->id)->isActive());
    }

    public function testANameThatMakesNoSlugAsksForTheSlug(): void
    {
        try {
            SlugText::made(new TransliteratingSlugger(), '!!!');
            self::fail('A slug was made of nothing.');
        } catch (InvalidOrganization $invalidOrganization) {
            self::assertStringEndsWith(' Give the slug.', $invalidOrganization->getMessage());
            self::assertGreaterThan(\strlen(' Give the slug.'), \strlen($invalidOrganization->getMessage()));
        }

        self::assertSame('Acme', OrganizationName::fromString("Acme\n")->value);
    }

    public function testAnEventIsDispatchedOnlyWhenThereIsADispatcher(): void
    {
        $work = new UnitOfWork(new InMemoryOrganizationStore());
        self::assertSame('done', $work->run(static function (EventRecorder $recorder): string {
            $recorder->record(new stdClass());

            return 'done';
        }));

        $events = new RecordingDispatcher();
        new UnitOfWork(new InMemoryOrganizationStore(), $events)->run(static function (EventRecorder $recorder): void {
            $recorder->record(new stdClass());
        });
        self::assertCount(1, $events->events);
    }

    public function testATeamIsCreatedAndRenamedOnlyIntoAFreeSlug(): void
    {
        $store = new RacingStore();
        $stage = new Stage($store);
        $acme = $stage->organizations->create('Acme', Stage::member('ann'));
        $design = $stage->teams->create($acme->id, Stage::member('ann'), 'Design');
        $build = $stage->teams->create($acme->id, Stage::member('ann'), 'Build');
        $saves = $store->teamSaves;

        try {
            $stage->teams->create($acme->id, Stage::member('ann'), 'Other', 'design');
            self::fail('A team slug was taken twice.');
        } catch (SlugTaken) {
        }

        try {
            $stage->teams->rename($build->id, Stage::member('ann'), 'Build', 'design');
            self::fail('A team slug was taken twice.');
        } catch (SlugTaken) {
        }

        self::assertSame($saves, $store->teamSaves);
        self::assertSame('design', $stage->teams->get($design->id)->slug->value);
    }

    public function testATeamIsLeftUnderTheLockOfItsOrganization(): void
    {
        $store = new RacingStore();
        $stage = new Stage($store);
        $acme = $stage->organizations->create('Acme', Stage::member('ann'));
        $team = $stage->teams->create($acme->id, Stage::member('ann'), 'Design');
        $stage->members->add($acme->id, Stage::member('ann'), Stage::member('bob'), ['member']);
        $stage->teams->addMember($team->id, Stage::member('ann'), Stage::member('bob'), ['member']);

        $store->locks = 0;

        $stage->teams->removeMember($team->id, Stage::member('bob'), Stage::member('bob'));

        self::assertSame(1, $store->locks);
    }

    public function testAMemberOfTheOrganizationOutsideTheTeamManagesNothing(): void
    {
        $stage = new Stage();
        $acme = $stage->acme();
        $design = $stage->teams->create($acme->id, Stage::member('bob'), 'Design');

        try {
            $stage->teams->rename($design->id, Stage::member('cam'), 'Mine');
            self::fail('A member outside the team renamed it.');
        } catch (PermissionDenied $permissionDenied) {
            self::assertSame('The permission "team.manage" is required.', $permissionDenied->getMessage());
        }
    }

    public function testAMaintainerChangesNoRoleIntoAWiderOne(): void
    {
        $stage = new Stage();
        $acme = $stage->acme();
        $design = $stage->teams->create($acme->id, Stage::member('bob'), 'Design');
        $stage->teams->addMember($design->id, Stage::member('bob'), Stage::member('cam'), ['maintainer']);
        $stage->members->add($acme->id, Stage::member('ann'), Stage::member('dan'), ['member']);
        $stage->teams->addMember($design->id, Stage::member('bob'), Stage::member('dan'), ['member']);

        try {
            $stage->teams->changeRoles($design->id, Stage::member('cam'), Stage::member('dan'), ['reviewer']);
            self::fail('A maintainer granted a wider role.');
        } catch (PermissionDenied $permissionDenied) {
            self::assertSame('A member grants, changes or removes only roles whose permissions they hold themselves.', $permissionDenied->getMessage());
        }

        self::assertSame(['member'], $stage->teams->membership($design->id, Stage::member('dan'))?->roles->names);
    }
}
