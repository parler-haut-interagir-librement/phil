<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Domain\Model\Invitation;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Team;
use Phil\OrganizationsBundle\Domain\Model\TeamMembership;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\OrganizationsBundle\Infrastructure\Memory\InMemoryOrganizationStore;
use Phil\Slug\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(InMemoryOrganizationStore::class)]
final class InMemoryStoreTest extends StoreTestCase
{
    public function testResetForgetsEverything(): void
    {
        $store = new InMemoryOrganizationStore();
        $store->saveOrganization(self::organization('o1', 'acme'));
        $store->saveMembership(self::membership('o1', 'ann', ['owner']));
        $store->saveTeam(self::team('t1', 'o1', 'design'));
        $store->saveTeamMembership(self::teamMembership('t1', 'ann', ['member']));
        $store->addInvitation(self::invitation('i1', 'o1', 1, 1000, 2000), '$hash$1');

        $store->reset();

        self::assertSame([], $store->organizations(null, 10));
        self::assertSame([], $store->membershipsOf(self::member('ann')));
        self::assertSame([], $store->teamMembershipsOf(self::member('ann')));
        self::assertNull($store->invitationByHash(['$hash$1']));
        self::assertNull($store->team(TeamId::fromString('t1')));
    }

    public function testAMembershipOfADeletedTeamIsNoLongerListed(): void
    {
        $store = new InMemoryOrganizationStore();
        $store->saveTeamMembership(self::teamMembership('t1', 'ann', ['member']));

        self::assertSame([], $store->teamMembershipsOf(self::member('ann')));
    }

    public function testNothingReadFromTheStoreChangesIt(): void
    {
        $store = new InMemoryOrganizationStore();
        $acme = self::organization('o1', 'acme');
        $acme->useDomain(EmailDomain::fromString('acme.org'));

        $store->saveOrganization($acme);
        $store->saveMembership(self::membership('o1', 'ann', ['member']));
        $store->saveTeam(self::team('t1', 'o1', 'design'));
        $store->saveTeamMembership(self::teamMembership('t1', 'ann', ['member']));
        $store->addInvitation(self::invitation('i1', 'o1', 3, 1000, 5000), '$hash$1');

        $rename = static fn (?object $organization) => $organization instanceof Organization ? $organization->rename(OrganizationName::fromString('Changed')) : self::fail('Nothing was read.');
        $roles = static fn (?object $membership) => $membership instanceof Membership || $membership instanceof TeamMembership ? $membership->changeRoles(Roles::of(['owner'])) : self::fail('Nothing was read.');
        $renameTeam = static fn (?object $team) => $team instanceof Team ? $team->rename(OrganizationName::fromString('Changed')) : self::fail('Nothing was read.');
        $use = static fn (?object $invitation) => $invitation instanceof Invitation ? $invitation->use() : self::fail('Nothing was read.');

        $rename($store->organization(self::id('o1')));
        $rename($store->organizationBySlug(Slug::fromString('acme')));
        $rename($store->organizations(null, 10)[0]);
        $rename($store->organizationsWithDomain(EmailDomain::fromString('acme.org'))[0]);
        $roles($store->membership(self::id('o1'), self::member('ann')));
        $roles($store->memberships(self::id('o1'), 10)[0]);
        $roles($store->membershipsOf(self::member('ann'))[0]);
        $renameTeam($store->team(TeamId::fromString('t1')));
        $renameTeam($store->teamBySlug(self::id('o1'), Slug::fromString('design')));
        $renameTeam($store->teams(self::id('o1'))[0]);
        $roles($store->teamMembership(TeamId::fromString('t1'), self::member('ann')));
        $roles($store->teamMemberships(TeamId::fromString('t1'))[0]);
        $roles($store->teamMembershipsOf(self::member('ann'))[0]);
        $use($store->invitation(InvitationId::fromString('i1')));
        $use($store->invitationByHash(['$hash$1']));
        $use($store->invitations(self::id('o1'))[0]);
        $use($store->invitationsCreatedBy(self::member('ann'))[0]);

        self::assertSame('Acme', $store->organization(self::id('o1'))?->name->value);
        self::assertSame(['member'], $store->membership(self::id('o1'), self::member('ann'))?->roles->names);
        self::assertSame('Design', $store->team(TeamId::fromString('t1'))?->name->value);
        self::assertSame(['member'], $store->teamMembership(TeamId::fromString('t1'), self::member('ann'))?->roles->names);
        self::assertSame(0, $store->invitation(InvitationId::fromString('i1'))?->uses);
    }

    public function testNothingGivenToTheStoreIsChangedByTheCaller(): void
    {
        $store = new InMemoryOrganizationStore();
        $membership = self::membership('o1', 'ann', ['member']);
        $team = self::team('t1', 'o1', 'design');
        $teamMembership = self::teamMembership('t1', 'ann', ['member']);
        $invitation = self::invitation('i1', 'o1', 3, 1000, 5000);
        $store->saveMembership($membership);
        $store->saveTeam($team);
        $store->saveTeamMembership($teamMembership);
        $store->addInvitation($invitation, '$hash$1');

        $membership->changeRoles(Roles::of(['owner']));
        $team->rename(OrganizationName::fromString('Changed'));
        $teamMembership->changeRoles(Roles::of(['owner']));
        $invitation->use();

        self::assertSame(['member'], $store->membership(self::id('o1'), self::member('ann'))?->roles->names);
        self::assertSame('Design', $store->team(TeamId::fromString('t1'))?->name->value);
        self::assertSame(['member'], $store->teamMembership(TeamId::fromString('t1'), self::member('ann'))?->roles->names);
        self::assertSame(0, $store->invitation(InvitationId::fromString('i1'))?->uses);
    }

    public function testATransactionThatThrowsTakesBackTheUseOfAnInvitation(): void
    {
        $store = new InMemoryOrganizationStore();
        $store->addInvitation(self::invitation('i1', 'o1', 3, 1000, 5000), '$hash$1');

        try {
            $store->transactional(static function () use ($store): never {
                $store->useInvitation(InvitationId::fromString('i1'), new DateTimeImmutable('@2000'));

                throw new RuntimeException('Lost.');
            });
        } catch (RuntimeException) {
            self::assertSame(0, $store->invitation(InvitationId::fromString('i1'))?->uses);
        }
    }

    public function testAPurgedInvitationLeavesItsHashAndKeepsTheOthers(): void
    {
        $store = new InMemoryOrganizationStore();
        $store->addInvitation(self::invitation('i1', 'o1', 1, 1000, 2000), '$hash$1');
        $store->addInvitation(self::invitation('i2', 'o1', 1, 1000, 9000), '$hash$2');

        self::assertSame(1, $store->purgeInvitations(new DateTimeImmutable('@2000')));
        self::assertSame('i2', $store->invitationByHash(['$hash$2'])?->id->value);

        $store->addInvitation(self::invitation('i1', 'o1', 1, 3000, 9000), '$hash$9');
        self::assertNull($store->invitationByHash(['$hash$1']));
        self::assertSame('i1', $store->invitationByHash(['$hash$9'])?->id->value);
    }

    #[Override]
    protected function store(): OrganizationStore
    {
        return new InMemoryOrganizationStore();
    }
}
