<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Invitation;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Team;
use Phil\OrganizationsBundle\Domain\Model\TeamMembership;
use Phil\OrganizationsBundle\Domain\Status\InvitationState;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\Status\OrganizationStatus;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\Slug\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Organization::class)]
#[CoversClass(Membership::class)]
#[CoversClass(Team::class)]
#[CoversClass(TeamMembership::class)]
#[CoversClass(Invitation::class)]
final class ModelTest extends TestCase
{
    public function testAnOrganizationIsCreatedActive(): void
    {
        $organization = Organization::create(OrganizationId::fromString('o1'), OrganizationName::fromString('Acme'), Slug::fromString('acme'), new DateTimeImmutable('2026-11-17 10:00:00.5'));

        self::assertSame(OrganizationStatus::Active, $organization->status);
        self::assertTrue($organization->isActive());
        self::assertNull($organization->archivedAt);
        self::assertNull($organization->emailDomain);
        self::assertSame('000000', $organization->createdAt->format('u'));
    }

    public function testAnOrganizationIsRenamedArchivedAndRestored(): void
    {
        $organization = Organization::create(OrganizationId::fromString('o1'), OrganizationName::fromString('Acme'), Slug::fromString('acme'), new DateTimeImmutable('@100'));

        $organization->rename(OrganizationName::fromString('Acme Guild'));
        self::assertSame('Acme Guild', $organization->name->value);
        self::assertSame('acme', $organization->slug->value);

        $organization->rename(OrganizationName::fromString('Acme Two'), Slug::fromString('acme-two'));
        self::assertSame('acme-two', $organization->slug->value);

        $organization->archive(new DateTimeImmutable('@200.5'));
        self::assertSame(OrganizationStatus::Archived, $organization->status);
        self::assertFalse($organization->isActive());
        self::assertSame(200, $organization->archivedAt?->getTimestamp());

        $organization->restore();
        self::assertTrue($organization->isActive());
        self::assertNull($organization->archivedAt);

        $organization->useDomain(EmailDomain::fromString('acme.org'));
        self::assertSame('acme.org', $organization->emailDomain?->value);
    }

    public function testARestoredOrganizationIsArchivedWhenItHasAMoment(): void
    {
        $archived = Organization::restored(OrganizationId::fromString('o1'), OrganizationName::fromString('Acme'), Slug::fromString('acme'), new DateTimeImmutable('@1'), new DateTimeImmutable('@2'), null);
        $active = Organization::restored(OrganizationId::fromString('o1'), OrganizationName::fromString('Acme'), Slug::fromString('acme'), new DateTimeImmutable('@1'), null, EmailDomain::fromString('a.org'));

        self::assertSame(OrganizationStatus::Archived, $archived->status);
        self::assertSame(OrganizationStatus::Active, $active->status);
        self::assertSame('a.org', $active->emailDomain?->value);
    }

    public function testMembershipsChangeRoles(): void
    {
        $membership = Membership::join(OrganizationId::fromString('o1'), MemberId::fromString('ann'), Roles::of(['member']), JoinMethod::Added, new DateTimeImmutable('@10.9'));
        $membership->changeRoles(Roles::of(['admin']));

        self::assertSame(['admin'], $membership->roles->names);
        self::assertSame(10, $membership->joinedAt->getTimestamp());
        self::assertSame('000000', $membership->joinedAt->format('u'));
        self::assertSame(JoinMethod::Added, $membership->method);

        $team = TeamMembership::join(TeamId::fromString('t1'), MemberId::fromString('ann'), Roles::of(['member']), new DateTimeImmutable('@10.9'));
        $team->changeRoles(Roles::of(['maintainer']));
        self::assertSame(['maintainer'], $team->roles->names);
        self::assertSame('000000', $team->joinedAt->format('u'));
        self::assertSame(['member'], TeamMembership::restored(TeamId::fromString('t1'), MemberId::fromString('ann'), Roles::of(['member']), new DateTimeImmutable('@1'))->roles->names);
    }

    public function testATeamIsRenamed(): void
    {
        $team = Team::create(TeamId::fromString('t1'), OrganizationId::fromString('o1'), OrganizationName::fromString('Design'), Slug::fromString('design'), new DateTimeImmutable('@5.5'));
        self::assertSame('000000', $team->createdAt->format('u'));

        $team->rename(OrganizationName::fromString('Product design'));
        self::assertSame('design', $team->slug->value);

        $team->rename(OrganizationName::fromString('Product design'), Slug::fromString('product-design'));
        self::assertSame('Product design', $team->name->value);
        self::assertSame('product-design', $team->slug->value);
    }

    public function testAnInvitationGoesThroughItsStates(): void
    {
        $now = new DateTimeImmutable('@1000');
        $invitation = $this->invitation(2, $now, new DateTimeImmutable('@2000'));

        self::assertSame(InvitationState::Usable, $invitation->stateAt($now));
        self::assertSame(InvitationState::Usable, $invitation->stateAt(new DateTimeImmutable('@1999')));
        self::assertSame(InvitationState::Expired, $invitation->stateAt(new DateTimeImmutable('@2000')));

        $invitation->use();
        self::assertSame(InvitationState::Usable, $invitation->stateAt($now));
        $invitation->use();
        self::assertSame(2, $invitation->uses);
        self::assertSame(InvitationState::UsedUp, $invitation->stateAt($now));

        $invitation->revoke(new DateTimeImmutable('@1500.5'));
        self::assertSame(InvitationState::Revoked, $invitation->stateAt($now));
        self::assertSame(1500, $invitation->revokedAt?->getTimestamp());

        $invitation->revoke(new DateTimeImmutable('@1600'));
        self::assertSame(1500, $invitation->revokedAt?->getTimestamp());

        self::assertSame('ann', $invitation->createdBy?->value);
        $invitation->forgetCreator();
        self::assertNull($invitation->createdBy);
    }

    public function testAnInvitationIsUsedAtLeastOnceAndAtMostTenThousandTimes(): void
    {
        $now = new DateTimeImmutable('@1000');
        self::assertSame(10_000, $this->invitation(10_000, $now, new DateTimeImmutable('@1001'))->maxUses);

        foreach ([0, 10_001] as $uses) {
            try {
                $this->invitation($uses, $now, new DateTimeImmutable('@2000'));
                self::fail('The uses were taken.');
            } catch (InvalidOrganization $invalidOrganization) {
                self::assertSame('An invitation is used 1 to 10000 times.', $invalidOrganization->getMessage());
            }
        }
    }

    public function testAnInvitationExpiresAfterItIsCreated(): void
    {
        $this->expectException(InvalidOrganization::class);
        $this->expectExceptionMessageIsOrContains('An invitation expires after it is created.');

        $this->invitation(1, new DateTimeImmutable('@1000'), new DateTimeImmutable('@1000.9'));
    }

    public function testAnInvitationKeepsItsMomentsToTheSecond(): void
    {
        $invitation = $this->invitation(1, new DateTimeImmutable('@1000.5'), new DateTimeImmutable('@2000.5'));

        self::assertSame('000000', $invitation->createdAt->format('u'));
        self::assertSame('000000', $invitation->expiresAt->format('u'));
        self::assertSame(0, $invitation->uses);
        self::assertNull($invitation->revokedAt);
    }

    private function invitation(int $uses, DateTimeImmutable $now, DateTimeImmutable $expiresAt): Invitation
    {
        return Invitation::create(InvitationId::fromString('i1'), OrganizationId::fromString('o1'), Roles::of(['member']), $uses, $now, $expiresAt, MemberId::fromString('ann'));
    }
}
