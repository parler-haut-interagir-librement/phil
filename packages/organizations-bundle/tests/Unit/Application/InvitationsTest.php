<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Application;

use Phil\AccessLinks\LinkToken;
use Phil\AccessLinks\TokenHasher;
use Phil\OrganizationsBundle\Application\Exception\AlreadyMember;
use Phil\OrganizationsBundle\Application\Exception\InvitationNotUsable;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\Exception\UnknownInvitation;
use Phil\OrganizationsBundle\Application\InvitationPolicy;
use Phil\OrganizationsBundle\Application\Invitations;
use Phil\OrganizationsBundle\Application\IssuedInvitation;
use Phil\OrganizationsBundle\Domain\Event\InvitationAccepted;
use Phil\OrganizationsBundle\Domain\Event\InvitationCreated;
use Phil\OrganizationsBundle\Domain\Event\InvitationRevoked;
use Phil\OrganizationsBundle\Domain\Event\MemberJoined;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Tests\Fixtures\RacingStore;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;

#[CoversClass(Invitations::class)]
#[CoversClass(InvitationPolicy::class)]
#[CoversClass(IssuedInvitation::class)]
#[CoversClass(InvitationNotUsable::class)]
#[CoversClass(UnknownInvitation::class)]
final class InvitationsTest extends TestCase
{
    private Stage $stage;

    private Organization $acme;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->acme = $this->stage->acme();
    }

    public function testCreateKeepsOnlyTheHash(): void
    {
        $issued = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['member']);
        $invitation = $issued->invitation;

        self::assertSame($issued->token, LinkToken::parse($issued->token));
        self::assertSame(['member'], $invitation->roles->names);
        self::assertSame(1, $invitation->maxUses);
        self::assertSame(0, $invitation->uses);
        self::assertSame('bob', $invitation->createdBy?->value);
        self::assertSame($this->stage->clock->now()->getTimestamp() + 604_800, $invitation->expiresAt->getTimestamp());
        self::assertEquals([new InvitationCreated($this->acme->id->value, $invitation->id->value, 'bob')], $this->stage->events->events);
        self::assertEquals($invitation, $this->stage->store->invitationByHash([new TokenHasher(Stage::KEY)->hash($issued->token)]));
        self::assertNull($this->stage->store->invitationByHash([$issued->token]));
        self::assertEquals([$invitation], $this->stage->invitations->invitations($this->acme->id));
    }

    public function testExpiryAndUsesWithinThePolicy(): void
    {
        $now = $this->stage->clock->now();
        $issued = $this->stage->invitations->create($this->acme->id, Stage::member('ann'), ['admin'], $now->modify('+30 days'), 100);

        self::assertSame(100, $issued->invitation->maxUses);
        self::assertSame($now->getTimestamp() + 2_592_000, $issued->invitation->expiresAt->getTimestamp());

        foreach ([[$now->modify('+30 days +1 second'), 1, 'An invitation lasts at most 2592000 seconds.'], [null, 0, 'An invitation is used 1 to 100 times.'], [null, 101, 'An invitation is used 1 to 100 times.']] as [$expiry, $uses, $message]) {
            try {
                $this->stage->invitations->create($this->acme->id, Stage::member('ann'), ['member'], $expiry, $uses);
                self::fail('An invitation went past the policy.');
            } catch (InvalidOrganization $invalidOrganization) {
                self::assertSame($message, $invalidOrganization->getMessage());
            }
        }
    }

    public function testThePolicyIsConsistent(): void
    {
        $policy = new InvitationPolicy(60, 60, 5, 5);
        self::assertSame([60, 60, 5, 5], [$policy->defaultLifetime, $policy->maxLifetime, $policy->defaultUses, $policy->maxUses]);
        self::assertSame(10_000, new InvitationPolicy(maxUses: 10_000)->maxUses);

        foreach ([static fn (): InvitationPolicy => new InvitationPolicy(61, 60), static fn (): InvitationPolicy => new InvitationPolicy(defaultUses: 6, maxUses: 5), static fn (): InvitationPolicy => new InvitationPolicy(maxUses: 10_001)] as $make) {
            try {
                $make();
                self::fail('A contradictory policy was taken.');
            } catch (InvalidOrganization) {
            }
        }
    }

    public function testOnlyThoseWhoInviteGrantTheirOwnRolesAtMost(): void
    {
        try {
            $this->stage->invitations->create($this->acme->id, Stage::member('cam'), ['member']);
            self::fail('A member invited.');
        } catch (PermissionDenied $permissionDenied) {
            self::assertSame('The permission "members.invite" is required.', $permissionDenied->getMessage());
        }

        try {
            $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['owner']);
            self::fail('An admin invited an owner.');
        } catch (PermissionDenied $permissionDenied) {
            self::assertSame('A member grants, changes or removes only roles whose permissions they hold themselves.', $permissionDenied->getMessage());
        }

        self::assertSame([], $this->stage->invitations->invitations($this->acme->id));
    }

    public function testAccept(): void
    {
        $issued = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['member', 'billing'], null, 2);
        $this->stage->events->clear();

        self::assertEquals($issued->invitation, $this->stage->invitations->preview($issued->token));

        $membership = $this->stage->invitations->accept($issued->token, Stage::member('dan'));

        self::assertSame(['billing', 'member'], $membership->roles->names);
        self::assertSame(JoinMethod::Invitation, $membership->method);
        self::assertSame(['billing', 'member'], $this->stage->members->membership($this->acme->id, Stage::member('dan'))?->roles->names);
        self::assertSame(1, $this->stage->store->invitation($issued->invitation->id)?->uses);
        self::assertEquals([
            new InvitationAccepted($this->acme->id->value, $issued->invitation->id->value, 'dan'),
            new MemberJoined($this->acme->id->value, 'dan', ['billing', 'member'], JoinMethod::Invitation),
        ], $this->stage->events->events);

        $this->stage->invitations->accept($issued->token, Stage::member('eve'));
        $this->assertNotUsable($issued->token, InvitationNotUsable::USED_UP);
    }

    public function testAMemberAcceptsNothing(): void
    {
        $issued = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['member']);

        try {
            $this->stage->invitations->accept($issued->token, Stage::member('cam'));
            self::fail('A member joined twice.');
        } catch (AlreadyMember) {
        }

        self::assertSame(0, $this->stage->store->invitation($issued->invitation->id)?->uses);
    }

    public function testAMalformedOrUnknownToken(): void
    {
        $this->assertNotUsable('not a token', InvitationNotUsable::UNKNOWN);
        $this->assertNotUsable(LinkToken::generate(new Randomizer()), InvitationNotUsable::UNKNOWN);

        try {
            $this->stage->invitations->preview('not a token');
            self::fail('A malformed token was previewed.');
        } catch (InvitationNotUsable $invitationNotUsable) {
            self::assertSame(InvitationNotUsable::UNKNOWN, $invitationNotUsable->reason);
        }
    }

    public function testExpired(): void
    {
        $issued = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['member'], $this->stage->clock->now()->modify('+1 hour'));
        $this->stage->clock->modify('+1 hour');

        $this->assertNotUsable($issued->token, InvitationNotUsable::EXPIRED);
    }

    public function testRevoke(): void
    {
        $issued = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['member']);
        $this->stage->events->clear();

        $this->stage->invitations->revoke($this->acme->id, $issued->invitation->id, Stage::member('ann'));
        $this->stage->invitations->revoke($this->acme->id, $issued->invitation->id, Stage::member('ann'));

        self::assertEquals([new InvitationRevoked($this->acme->id->value, $issued->invitation->id->value, 'ann')], $this->stage->events->events);
        self::assertSame($this->stage->clock->now()->getTimestamp(), $this->stage->store->invitation($issued->invitation->id)?->revokedAt?->getTimestamp());
        $this->assertNotUsable($issued->token, InvitationNotUsable::REVOKED);

        try {
            $this->stage->invitations->preview($issued->token);
            self::fail('A revoked invitation was previewed.');
        } catch (InvitationNotUsable $invitationNotUsable) {
            self::assertSame(InvitationNotUsable::REVOKED, $invitationNotUsable->reason);
        }
    }

    public function testRevokeAnotherOrganizationsInvitation(): void
    {
        $other = $this->stage->organizations->create('Other', Stage::member('ann'));
        $issued = $this->stage->invitations->create($other->id, Stage::member('ann'), ['member']);

        foreach ([$issued->invitation->id, InvitationId::fromString('ghost')] as $id) {
            try {
                $this->stage->invitations->revoke($this->acme->id, $id, Stage::member('ann'));
                self::fail('An invitation of another organization was revoked.');
            } catch (UnknownInvitation $unknownInvitation) {
                self::assertSame(\sprintf('No invitation "%s".', $id->value), $unknownInvitation->getMessage());
            }
        }

        self::assertNull($this->stage->store->invitation($issued->invitation->id)?->revokedAt);

        $this->expectException(PermissionDenied::class);

        $this->stage->invitations->revoke($this->acme->id, $issued->invitation->id, Stage::member('cam'));
    }

    public function testAnArchivedOrganizationTakesNobody(): void
    {
        $issued = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['member']);
        $this->stage->organizations->archive($this->acme->id, Stage::member('ann'));

        $this->assertNotUsable($issued->token, InvitationNotUsable::ARCHIVED);

        try {
            $this->stage->invitations->preview($issued->token);
            self::fail('An invitation of an archived organization was previewed.');
        } catch (InvitationNotUsable $invitationNotUsable) {
            self::assertSame(InvitationNotUsable::ARCHIVED, $invitationNotUsable->reason);
        }
    }

    public function testTheCreatorMustStillBeAbleToGrantTheRoles(): void
    {
        $byBob = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['admin']);
        $byAnn = $this->stage->invitations->create($this->acme->id, Stage::member('ann'), ['member']);
        $byCam = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['member']);
        $this->stage->members->changeRoles($this->acme->id, Stage::member('ann'), Stage::member('bob'), ['member']);

        $this->assertNotUsable($byBob->token, InvitationNotUsable::CREATOR);
        $this->assertNotUsable($byCam->token, InvitationNotUsable::CREATOR);

        $this->stage->members->changeRoles($this->acme->id, Stage::member('ann'), Stage::member('bob'), ['admin']);
        $this->stage->members->add($this->acme->id, Stage::member('ann'), Stage::member('eve'), ['owner']);
        $this->stage->members->changeRoles($this->acme->id, Stage::member('eve'), Stage::member('ann'), ['billing']);
        $this->assertNotUsable($byAnn->token, InvitationNotUsable::CREATOR);
    }

    public function testAnInvitationWithoutItsCreatorIsRefused(): void
    {
        $issued = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['member']);
        $this->stage->store->forgetInvitationCreator(Stage::member('bob'));

        $this->assertNotUsable($issued->token, InvitationNotUsable::CREATOR);
    }

    public function testAnotherKeyStillFindsTheInvitationsOfTheOneItReplaced(): void
    {
        $issued = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['member']);
        $rotated = new Invitations($this->stage->store, $this->stage->work, $this->stage->access, new TokenHasher('a-new-key-of-thirty-two-bytes-at-least!', Stage::KEY), $this->stage->clock);

        self::assertSame(['member'], $rotated->accept($issued->token, Stage::member('dan'))->roles->names);
    }

    public function testPurge(): void
    {
        $short = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['member'], $this->stage->clock->now()->modify('+1 day'));
        $revoked = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['member']);
        $kept = $this->stage->invitations->create($this->acme->id, Stage::member('bob'), ['member'], $this->stage->clock->now()->modify('+20 days'));
        $this->stage->clock->modify('+36 hours');
        $this->stage->invitations->revoke($this->acme->id, $revoked->invitation->id, Stage::member('bob'));
        $this->stage->clock->modify('+12 hours');

        self::assertSame(0, $this->stage->invitations->purge(86_401));
        self::assertSame(1, $this->stage->invitations->purge(86_400));
        self::assertNull($this->stage->store->invitation($short->invitation->id));
        self::assertNotNull($this->stage->store->invitation($revoked->invitation->id));
        self::assertSame(0, $this->stage->invitations->purge(43_201));
        self::assertSame(1, $this->stage->invitations->purge(43_200));
        self::assertNull($this->stage->store->invitation($revoked->invitation->id));
        self::assertSame(0, $this->stage->invitations->purge(0));
        self::assertNotNull($this->stage->store->invitation($kept->invitation->id));
    }

    public function testTheLastUseIsTakenOnce(): void
    {
        $store = new RacingStore();
        $stage = new Stage($store);
        $acme = $stage->acme();
        $issued = $stage->invitations->create($acme->id, Stage::member('bob'), ['member']);
        $store->loseUses = true;
        $stage->events->clear();

        try {
            $stage->invitations->accept($issued->token, Stage::member('dan'));
            self::fail('A used-up invitation was used again.');
        } catch (InvitationNotUsable $invitationNotUsable) {
            self::assertSame(InvitationNotUsable::USED_UP, $invitationNotUsable->reason);
            self::assertSame('This invitation cannot be used.', $invitationNotUsable->getMessage());
        }

        self::assertNull($stage->members->membership($acme->id, Stage::member('dan')));
        self::assertSame([], $stage->events->events);
        self::assertGreaterThan(0, $store->locks);
    }

    private function assertNotUsable(string $token, string $reason): void
    {
        try {
            $this->stage->invitations->accept($token, Stage::member('zed'));
            self::fail('An invitation that cannot be used was used.');
        } catch (InvitationNotUsable $invitationNotUsable) {
            self::assertSame($reason, $invitationNotUsable->reason);
            self::assertSame('This invitation cannot be used.', $invitationNotUsable->getMessage());
        }

        self::assertNull($this->stage->members->membership($this->acme->id, Stage::member('zed')));
    }
}
