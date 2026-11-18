<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Application;

use Phil\OrganizationsBundle\Application\MemberErasure;
use Phil\OrganizationsBundle\Application\OwnershipCheck;
use Phil\OrganizationsBundle\Domain\Event\InvitationRevoked;
use Phil\OrganizationsBundle\Domain\Event\MemberLeft;
use Phil\OrganizationsBundle\Domain\Event\OrganizationLeftWithoutOwner;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRemoved;
use Phil\OrganizationsBundle\Domain\Status\LeaveReason;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemberErasure::class)]
#[CoversClass(OwnershipCheck::class)]
final class ErasureAndOwnershipTest extends TestCase
{
    public function testTheLastOwnerIsErasedAndTheOrganizationAnnounced(): void
    {
        $stage = new Stage();
        $acme = $stage->acme();
        $other = $stage->organizations->create('Other', Stage::member('bob'));
        $stage->members->add($other->id, Stage::member('bob'), Stage::member('ann'), ['owner']);
        $team = $stage->teams->create($acme->id, Stage::member('ann'), 'Design');
        $stage->teams->addMember($team->id, Stage::member('ann'), Stage::member('ann'), ['maintainer']);
        $pending = $stage->invitations->create($acme->id, Stage::member('ann'), ['member']);
        $expired = $stage->invitations->create($acme->id, Stage::member('ann'), ['member'], $stage->clock->now()->modify('+1 hour'));
        $byBob = $stage->invitations->create($acme->id, Stage::member('bob'), ['member']);
        $stage->clock->modify('+2 hours');
        $stage->events->clear();

        $stage->erasure->erase(Stage::member('ann'));

        self::assertSame([], $stage->organizations->ofMember(Stage::member('ann')));
        self::assertSame([], $stage->store->teamMembershipsOf(Stage::member('ann')));
        self::assertSame(0, $stage->ownership->owners($acme->id));
        self::assertSame(1, $stage->ownership->owners($other->id));
        self::assertNull($stage->store->invitation($pending->invitation->id)?->createdBy);
        self::assertNotNull($stage->store->invitation($pending->invitation->id)?->revokedAt);
        self::assertNull($stage->store->invitation($expired->invitation->id)?->revokedAt);
        self::assertSame('bob', $stage->store->invitation($byBob->invitation->id)?->createdBy?->value);
        self::assertSame([], $stage->erasure->invitationsCreatedBy(Stage::member('ann')));
        self::assertEquals([
            new TeamMemberRemoved($acme->id->value, $team->id->value, 'ann', ['maintainer']),
            new MemberLeft($acme->id->value, 'ann', ['owner'], LeaveReason::Erased),
            new OrganizationLeftWithoutOwner($acme->id->value),
            new MemberLeft($other->id->value, 'ann', ['owner'], LeaveReason::Erased),
            new InvitationRevoked($acme->id->value, $pending->invitation->id->value),
        ], $stage->events->events);

        $stage->events->clear();
        $stage->erasure->erase(Stage::member('ann'));
        self::assertSame([], $stage->events->events);
    }

    public function testAnErasedMemberWhoWasNoOwnerLeavesNoOrphan(): void
    {
        $stage = new Stage();
        $stage->acme();

        $stage->erasure->erase(Stage::member('cam'));

        self::assertSame([MemberLeft::class], $stage->events->classes());
    }

    public function testTheRecommendationOfTwoOwners(): void
    {
        $stage = new Stage();
        $acme = $stage->acme();
        $other = $stage->organizations->create('Other', Stage::member('bob'));
        $stage->members->add($other->id, Stage::member('bob'), Stage::member('ann'), ['owner']);

        self::assertSame(2, $stage->ownership->recommended);
        self::assertFalse($stage->ownership->meetsRecommendation($acme->id));
        self::assertTrue($stage->ownership->meetsRecommendation($other->id));
        self::assertSame([$acme->id->value => 1], $stage->ownership->belowRecommendation());

        $stage->erasure->erase(Stage::member('ann'));
        self::assertSame([$acme->id->value => 0, $other->id->value => 1], $stage->ownership->belowRecommendation());
        self::assertSame([$acme->id->value => 0], $stage->ownership->belowRecommendation(1));
        self::assertSame([$acme->id->value => 0], new OwnershipCheck($stage->store, 1)->belowRecommendation());
    }
}
