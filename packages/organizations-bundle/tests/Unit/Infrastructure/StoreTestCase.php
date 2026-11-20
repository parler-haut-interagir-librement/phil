<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Contract\UnitStore;
use Phil\OrganizationsBundle\Application\Exception\SlugTaken;
use Phil\OrganizationsBundle\Domain\Model\Invitation;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Team;
use Phil\OrganizationsBundle\Domain\Model\TeamMembership;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\Model\UnitMembership;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\Status\OrganizationStatus;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\Slug\Slug;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * What every store promises, run against each of them.
 */
abstract class StoreTestCase extends TestCase
{
    abstract protected function store(): OrganizationStore;

    public function testOrganizationsAreAddedAndChanged(): void
    {
        $store = $this->store();
        $acme = self::organization('o1', 'acme', 'Acme Guild');

        self::assertNull($store->organization(self::id('o1')));
        $store->saveOrganization($acme);
        self::assertEquals($acme, $store->organization(self::id('o1')));
        self::assertEquals($acme, $store->organization(self::id('o1'), true));
        self::assertEquals($acme, $store->organizationBySlug(Slug::fromString('acme')));
        self::assertNull($store->organizationBySlug(Slug::fromString('other')));
        self::assertNull($store->organization(self::id('O1')));

        $acme->rename(OrganizationName::fromString('Acme Été'), Slug::fromString('acme-ete'));
        $acme->useDomain(EmailDomain::fromString('acme.org'));
        $acme->archive(new DateTimeImmutable('@2000'));

        $store->saveOrganization($acme);

        $read = $store->organization(self::id('o1'));
        self::assertEquals($acme, $read);
        self::assertSame('Acme Été', $read?->name->value);
        self::assertSame(2000, $read->archivedAt?->getTimestamp());
        self::assertSame(1000, $read->createdAt->getTimestamp());
        self::assertNull($store->organizationBySlug(Slug::fromString('acme')));
    }

    public function testCopiesGoInAndComeOut(): void
    {
        $store = $this->store();
        $acme = self::organization('o1', 'acme');
        $store->saveOrganization($acme);
        $acme->rename(OrganizationName::fromString('Changed'));

        self::assertSame('Acme', $store->organization(self::id('o1'))?->name->value);
    }

    public function testASlugBelongsToOneOrganization(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'acme'));
        $store->saveOrganization(self::organization('o2', 'other'));

        $moved = self::organization('o2', 'acme');

        try {
            $store->saveOrganization($moved);
            self::fail('A slug was taken twice.');
        } catch (SlugTaken $slugTaken) {
            self::assertSame('The slug "acme" is taken.', $slugTaken->getMessage());
        }
    }

    public function testASlugComparesByteForByte(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'acme'));
        $store->saveOrganization(self::organization('O1', 'acme-2'));

        self::assertSame('acme', $store->organization(self::id('o1'))?->slug->value);
        self::assertSame('acme-2', $store->organization(self::id('O1'))?->slug->value);
    }

    public function testOrganizationsAreListedBySlug(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o3', 'charlie'));
        $store->saveOrganization(self::organization('o1', 'alpha'));

        $archived = self::organization('o2', 'bravo');
        $archived->archive(new DateTimeImmutable('@3000'));
        $archived->useDomain(EmailDomain::fromString('acme.org'));

        $store->saveOrganization($archived);
        $withDomain = self::organization('o4', 'delta');
        $withDomain->useDomain(EmailDomain::fromString('acme.org'));

        $store->saveOrganization($withDomain);

        self::assertSame(['alpha', 'bravo', 'charlie', 'delta'], $this->slugs($store->organizations(null, 10)));
        self::assertSame(['alpha', 'bravo'], $this->slugs($store->organizations(null, 2)));
        self::assertSame(['charlie', 'delta'], $this->slugs($store->organizations(null, 10, 'bravo')));
        self::assertSame(['alpha', 'charlie', 'delta'], $this->slugs($store->organizations(OrganizationStatus::Active, 10)));
        self::assertSame(['bravo'], $this->slugs($store->organizations(OrganizationStatus::Archived, 10)));
        self::assertSame(['delta'], $this->slugs($store->organizationsWithDomain(EmailDomain::fromString('acme.org'))));
        self::assertSame([], $store->organizationsWithDomain(EmailDomain::fromString('other.org')));
    }

    public function testMemberships(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'acme'));
        $store->saveOrganization(self::organization('o2', 'other'));

        $ann = self::membership('o1', 'ann', ['owner', 'admin']);
        $store->saveMembership($ann);
        $store->saveMembership(self::membership('o1', 'bob', ['member']));
        $store->saveMembership(self::membership('o1', 'Bob', ['member']));
        $store->saveMembership(self::membership('o2', 'ann', ['member'], JoinMethod::Invitation));

        self::assertEquals($ann, $store->membership(self::id('o1'), self::member('ann')));
        self::assertNull($store->membership(self::id('o1'), self::member('cam')));
        self::assertSame(['Bob', 'ann', 'bob'], $this->members($store->memberships(self::id('o1'), 10)));
        self::assertSame(['bob'], $this->members($store->memberships(self::id('o1'), 10, 'ann')));
        self::assertSame(['Bob'], $this->members($store->memberships(self::id('o1'), 1)));
        self::assertSame(['o1', 'o2'], array_map(static fn (Membership $m): string => $m->organizationId->value, $store->membershipsOf(self::member('ann'))));
        self::assertSame(JoinMethod::Invitation, $store->membership(self::id('o2'), self::member('ann'))?->method);
        self::assertSame(3, $store->countMembers(self::id('o1')));
        self::assertSame(1, $store->countWithRole(self::id('o1'), 'owner'));
        self::assertSame(2, $store->countWithRole(self::id('o1'), 'member'));
        self::assertSame(0, $store->countWithRole(self::id('o1'), 'billing'));

        $ann->changeRoles(Roles::of(['member']));
        $store->saveMembership($ann);
        self::assertSame(['member'], $store->membership(self::id('o1'), self::member('ann'))?->roles->names);
        self::assertSame(0, $store->countWithRole(self::id('o1'), 'owner'));
        self::assertSame(3, $store->countWithRole(self::id('o1'), 'member'));
        self::assertSame(1000, $store->membership(self::id('o1'), self::member('ann'))?->joinedAt->getTimestamp());

        self::assertTrue($store->deleteMembership(self::id('o1'), self::member('ann')));
        self::assertFalse($store->deleteMembership(self::id('o1'), self::member('ann')));
        self::assertNull($store->membership(self::id('o1'), self::member('ann')));
        self::assertSame(2, $store->countWithRole(self::id('o1'), 'member'));
        self::assertNotNull($store->membership(self::id('o2'), self::member('ann')));
    }

    public function testOrganizationsWithFewerHolders(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'alpha'));
        $store->saveOrganization(self::organization('o2', 'bravo'));
        $store->saveOrganization(self::organization('o3', 'charlie'));
        $store->saveMembership(self::membership('o1', 'ann', ['owner']));
        $store->saveMembership(self::membership('o1', 'bob', ['owner']));
        $store->saveMembership(self::membership('o2', 'ann', ['owner']));
        $store->saveMembership(self::membership('o3', 'ann', ['member']));

        self::assertSame(['o3' => 0, 'o2' => 1], $store->organizationsWithFewer('owner', 2, 10));
        self::assertSame(['o3' => 0], $store->organizationsWithFewer('owner', 2, 1));
        self::assertSame(['o3' => 0], $store->organizationsWithFewer('owner', 1, 10));
        self::assertSame(['o3' => 0, 'o2' => 1, 'o1' => 2], $store->organizationsWithFewer('owner', 3, 10));
    }

    public function testTeams(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'acme'));
        $store->saveOrganization(self::organization('o2', 'other'));

        $design = self::team('t1', 'o1', 'design');
        $store->saveTeam($design);
        $store->saveTeam(self::team('t2', 'o1', 'build'));
        $store->saveTeam(self::team('t3', 'o2', 'design'));

        self::assertEquals($design, $store->team(TeamId::fromString('t1')));
        self::assertNull($store->team(TeamId::fromString('t9')));
        self::assertEquals($design, $store->teamBySlug(self::id('o1'), Slug::fromString('design')));
        self::assertSame('t3', $store->teamBySlug(self::id('o2'), Slug::fromString('design'))?->id->value);
        self::assertNull($store->teamBySlug(self::id('o1'), Slug::fromString('ghost')));
        self::assertSame(['build', 'design'], array_map(static fn (Team $t): string => $t->slug->value, $store->teams(self::id('o1'))));

        $design->rename(OrganizationName::fromString('Product'), Slug::fromString('product'));
        $store->saveTeam($design);
        self::assertSame('Product', $store->team(TeamId::fromString('t1'))?->name->value);
        self::assertSame(1000, $store->team(TeamId::fromString('t1'))->createdAt->getTimestamp());

        try {
            $store->saveTeam(self::team('t4', 'o1', 'build'));
            self::fail('A team slug was taken twice.');
        } catch (SlugTaken) {
        }

        self::assertNull($store->team(TeamId::fromString('t4')));
    }

    public function testTeamMemberships(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'acme'));
        $store->saveOrganization(self::organization('o2', 'other'));
        $store->saveTeam(self::team('t1', 'o1', 'design'));
        $store->saveTeam(self::team('t2', 'o1', 'build'));
        $store->saveTeam(self::team('t3', 'o2', 'design'));
        $store->saveMembership(self::membership('o1', 'ann', ['member']));
        $store->saveMembership(self::membership('o2', 'ann', ['member']));

        $cam = self::teamMembership('t1', 'cam', ['member']);
        $store->saveTeamMembership(self::teamMembership('t1', 'ann', ['maintainer']));
        $store->saveTeamMembership($cam);
        $store->saveTeamMembership(self::teamMembership('t2', 'ann', ['member']));
        $store->saveTeamMembership(self::teamMembership('t3', 'ann', ['member']));

        self::assertEquals($cam, $store->teamMembership(TeamId::fromString('t1'), self::member('cam')));
        self::assertNull($store->teamMembership(TeamId::fromString('t2'), self::member('cam')));
        self::assertSame(['ann', 'cam'], array_map(static fn (TeamMembership $m): string => $m->memberId->value, $store->teamMemberships(TeamId::fromString('t1'))));
        self::assertSame(['t1', 't2', 't3'], $this->teamsOf($store->teamMembershipsOf(self::member('ann'))));
        self::assertSame(['t1', 't2'], $this->teamsOf($store->teamMembershipsOf(self::member('ann'), self::id('o1'))));

        $cam->changeRoles(Roles::of(['maintainer', 'member']));
        $store->saveTeamMembership($cam);
        self::assertSame(['maintainer', 'member'], $store->teamMembership(TeamId::fromString('t1'), self::member('cam'))?->roles->names);

        self::assertTrue($store->deleteTeamMembership(TeamId::fromString('t1'), self::member('cam')));
        self::assertFalse($store->deleteTeamMembership(TeamId::fromString('t1'), self::member('cam')));

        self::assertTrue($store->deleteMembership(self::id('o1'), self::member('ann')));
        self::assertSame(['t3'], $this->teamsOf($store->teamMembershipsOf(self::member('ann'))));

        $store->saveTeamMembership(self::teamMembership('t2', 'bob', ['member']));
        self::assertTrue($store->deleteTeam(TeamId::fromString('t2')));
        self::assertFalse($store->deleteTeam(TeamId::fromString('t2')));
        self::assertSame([], $store->teamMemberships(TeamId::fromString('t2')));
        self::assertSame([], $store->teamMembershipsOf(self::member('bob')));
    }

    public function testInvitations(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'acme'));

        $first = self::invitation('i1', 'o1', 2, 1000, 5000);
        $second = self::invitation('i2', 'o1', 1, 2000, 5000);
        $store->addInvitation($first, '$hash$1');
        $store->addInvitation($second, '$hash$2');
        $store->addInvitation(self::invitation('i3', 'o2', 1, 3000, 5000, 'bob'), '$hash$3');

        self::assertEquals($first, $store->invitation(InvitationId::fromString('i1')));
        self::assertNull($store->invitation(InvitationId::fromString('i9')));
        self::assertEquals($first, $store->invitationByHash(['$hash$0', '$hash$1']));
        self::assertNull($store->invitationByHash(['$HASH$1']));
        self::assertSame(['i2', 'i1'], $this->invitationIds($store->invitations(self::id('o1'))));
        self::assertSame(['i2', 'i1'], $this->invitationIds($store->invitationsCreatedBy(self::member('ann'))));

        self::assertTrue($store->useInvitation(InvitationId::fromString('i1'), new DateTimeImmutable('@4999')));
        self::assertTrue($store->useInvitation(InvitationId::fromString('i1'), new DateTimeImmutable('@4999')));
        self::assertFalse($store->useInvitation(InvitationId::fromString('i1'), new DateTimeImmutable('@4999')));
        self::assertSame(2, $store->invitation(InvitationId::fromString('i1'))?->uses);
        self::assertFalse($store->useInvitation(InvitationId::fromString('i2'), new DateTimeImmutable('@5000')));
        self::assertFalse($store->useInvitation(InvitationId::fromString('i9'), new DateTimeImmutable('@1')));

        self::assertTrue($store->revokeInvitation(InvitationId::fromString('i2'), new DateTimeImmutable('@2500')));
        self::assertFalse($store->revokeInvitation(InvitationId::fromString('i2'), new DateTimeImmutable('@2600')));
        self::assertFalse($store->revokeInvitation(InvitationId::fromString('i9'), new DateTimeImmutable('@2600')));
        self::assertSame(2500, $store->invitation(InvitationId::fromString('i2'))?->revokedAt?->getTimestamp());
        self::assertFalse($store->useInvitation(InvitationId::fromString('i2'), new DateTimeImmutable('@2600')));

        self::assertSame(2, $store->forgetInvitationCreator(self::member('ann')));
        self::assertSame(0, $store->forgetInvitationCreator(self::member('ann')));
        self::assertNull($store->invitation(InvitationId::fromString('i1'))->createdBy);
        self::assertSame([], $store->invitationsCreatedBy(self::member('ann')));
        self::assertSame(['i3'], $this->invitationIds($store->invitationsCreatedBy(self::member('bob'))));
    }

    public function testChangingOneOrganizationLeavesTheOthersAlone(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'alpha', 'Alpha'));
        $store->saveOrganization(self::organization('o2', 'bravo', 'Bravo'));

        $alpha = $store->organization(self::id('o1'));
        self::assertNotNull($alpha);
        $alpha->rename(OrganizationName::fromString('Changed'), Slug::fromString('changed'));
        $store->saveOrganization($alpha);

        self::assertSame('Changed', $store->organization(self::id('o1'))?->name->value);
        self::assertSame('Bravo', $store->organization(self::id('o2'))?->name->value);
        self::assertSame('bravo', $store->organization(self::id('o2'))->slug->value);
    }

    public function testDeletingATeamOrItsMemberLeavesTheOtherTeamsAlone(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'acme'));
        $store->saveTeam(self::team('t1', 'o1', 'design'));
        $store->saveTeam(self::team('t2', 'o1', 'build'));
        $store->saveTeamMembership(self::teamMembership('t1', 'ann', ['member']));
        $store->saveTeamMembership(self::teamMembership('t1', 'bob', ['member']));
        $store->saveTeamMembership(self::teamMembership('t2', 'ann', ['member']));

        self::assertTrue($store->deleteTeamMembership(TeamId::fromString('t1'), self::member('ann')));
        self::assertNull($store->teamMembership(TeamId::fromString('t1'), self::member('ann')));
        self::assertNotNull($store->teamMembership(TeamId::fromString('t1'), self::member('bob')));
        self::assertNotNull($store->teamMembership(TeamId::fromString('t2'), self::member('ann')));

        self::assertTrue($store->deleteTeam(TeamId::fromString('t1')));
        self::assertNull($store->team(TeamId::fromString('t1')));
        self::assertNotNull($store->team(TeamId::fromString('t2')));
        self::assertNotNull($store->teamMembership(TeamId::fromString('t2'), self::member('ann')));
    }

    public function testAnInvitationWithoutACreatorIsKept(): void
    {
        $store = $this->store();
        $orphan = Invitation::restored(InvitationId::fromString('i1'), self::id('o1'), Roles::of(['member']), 1, 0, new DateTimeImmutable('@1000'), new DateTimeImmutable('@2000'), null, null);

        $store->addInvitation($orphan, '$hash$1');

        self::assertEquals($orphan, $store->invitation(InvitationId::fromString('i1')));
        self::assertNull($store->invitation(InvitationId::fromString('i1'))?->createdBy);
    }

    public function testInvitationsOfTheSameMomentComeNewestIdFirst(): void
    {
        $store = $this->store();
        $store->addInvitation(self::invitation('i0', 'o1', 1, 2000, 5000), '$hash$0');
        $store->addInvitation(self::invitation('i1', 'o1', 1, 1000, 5000), '$hash$1');
        $store->addInvitation(self::invitation('i2', 'o1', 1, 1000, 5000), '$hash$2');
        $store->addInvitation(self::invitation('i3', 'o1', 1, 1000, 5000), '$hash$3');

        self::assertSame(['i0', 'i3', 'i2', 'i1'], $this->invitationIds($store->invitations(self::id('o1'))));
        self::assertSame(['i0', 'i3', 'i2', 'i1'], $this->invitationIds($store->invitationsCreatedBy(self::member('ann'))));
    }

    public function testMembersAndTeamMembersComeInOrder(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'alpha'));
        $store->saveOrganization(self::organization('o2', 'bravo'));
        $store->saveTeam(self::team('t1', 'o1', 'design'));
        $store->saveTeam(self::team('t2', 'o1', 'build'));
        $store->saveMembership(self::membership('o2', 'ann', ['member']));
        $store->saveMembership(self::membership('o1', 'ann', ['member']));
        $store->saveTeamMembership(self::teamMembership('t2', 'ann', ['member']));
        $store->saveTeamMembership(self::teamMembership('t1', 'ann', ['member']));
        $store->saveTeamMembership(self::teamMembership('t1', 'cam', ['member']));
        $store->saveTeamMembership(self::teamMembership('t1', 'bob', ['member']));

        self::assertSame(['o1', 'o2'], array_map(static fn (Membership $m): string => $m->organizationId->value, $store->membershipsOf(self::member('ann'))));
        self::assertSame(['t1', 't2'], $this->teamsOf($store->teamMembershipsOf(self::member('ann'))));
        self::assertSame(['ann', 'bob', 'cam'], array_map(static fn (TeamMembership $m): string => $m->memberId->value, $store->teamMemberships(TeamId::fromString('t1'))));
    }

    public function testInvitationsArePurged(): void
    {
        $store = $this->store();
        $store->addInvitation(self::invitation('i1', 'o1', 1, 1000, 2000), '$hash$1');
        $store->addInvitation(self::invitation('i2', 'o1', 1, 1000, 9000), '$hash$2');
        $store->addInvitation(self::invitation('i3', 'o1', 1, 1000, 9000), '$hash$3');
        $store->revokeInvitation(InvitationId::fromString('i2'), new DateTimeImmutable('@3000'));

        self::assertSame(0, $store->purgeInvitations(new DateTimeImmutable('@1999')));
        self::assertSame(1, $store->purgeInvitations(new DateTimeImmutable('@2000')));
        self::assertNull($store->invitation(InvitationId::fromString('i1')));
        self::assertNull($store->invitationByHash(['$hash$1']));
        self::assertSame(0, $store->purgeInvitations(new DateTimeImmutable('@2999')));
        self::assertSame(1, $store->purgeInvitations(new DateTimeImmutable('@3000')));
        self::assertSame(['i3'], $this->invitationIds($store->invitations(self::id('o1'))));
    }

    public function testATransactionThatThrowsChangesNothing(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'acme'));

        self::assertSame('done', $store->transactional(static function () use ($store): string {
            $store->saveMembership(self::membership('o1', 'ann', ['owner']));

            return 'done';
        }));

        $caught = null;

        try {
            $store->transactional(static fn (): string => self::rollBack($store));
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException->getMessage();
        }

        self::assertSame('Rolled back.', $caught);

        self::assertNull($store->organization(self::id('o2')));
        self::assertNull($store->membership(self::id('o1'), self::member('bob')));
        self::assertNull($store->invitation(InvitationId::fromString('i1')));
        self::assertSame(1, $store->countWithRole(self::id('o1'), 'owner'));
    }

    public function testUnitsAreAddedRenamedAndArchived(): void
    {
        $store = $this->unitStore();
        $sales = self::unit('sales', 'o1', null);
        $east = self::unit('east', 'o1', $sales);

        self::assertNull($store->unit(UnitId::fromString('sales')));
        $store->saveUnit($sales);
        $store->saveUnit($east);
        self::assertEquals($sales, $store->unit(UnitId::fromString('sales')));
        self::assertEquals($east, $store->unit(UnitId::fromString('east')));
        self::assertNull($store->unit(UnitId::fromString('SALES')));

        $east->rename(OrganizationName::fromString('East coast'));
        $east->archive(new DateTimeImmutable('@2000'));

        $store->saveUnit($east);
        $read = self::found($store, 'east');

        self::assertSame('East coast', $read->name->value);
        self::assertSame(2000, $read->archivedAt?->getTimestamp());
        self::assertSame(1000, $read->createdAt->getTimestamp());
        self::assertSame('/sales/east/', $read->path->toString());

        $east->restore();
        $store->saveUnit($east);
        self::assertTrue(self::found($store, 'east')->isActive());
    }

    public function testTheUnitsOfAnOrganizationAreSortedByPath(): void
    {
        $store = $this->unitStore();
        [$a, $ab, $abc, $ab2, $b] = $this->smallTree($store);
        $store->saveUnit(self::unit('elsewhere', 'o2', null));

        self::assertSame(['a', 'ab-2', 'ab', 'abc', 'b'], self::unitIds($store->units(self::id('o1'))));
        self::assertSame(['ab', 'abc'], self::unitIds($store->units(self::id('o1'), $ab->path)));
        self::assertSame(['a', 'ab-2', 'ab', 'abc'], self::unitIds($store->units(self::id('o1'), $a->path)));
        self::assertSame([], $store->units(self::id('o2'), $a->path));
        self::assertSame(['a', 'b'], self::unitIds($store->children(self::id('o1'), null)));
        self::assertSame(['ab-2', 'ab'], self::unitIds($store->children(self::id('o1'), $a->id)));
        self::assertSame([], $store->children(self::id('o2'), $a->id));
        self::assertSame(['a', 'abc', 'b'], self::unitIds($store->unitsById([$b->id, $abc->id, $a->id, UnitId::fromString('nowhere')])));
        self::assertSame([], $store->unitsById([]));
        self::assertSame(5, $store->countUnits(self::id('o1')));
        self::assertSame(0, $store->countUnits(self::id('o3')));
        self::assertSame(3, $store->deepest(self::id('o1'), $a->path));
        self::assertSame(1, $store->deepest(self::id('o1'), $ab2->path->rebase($ab2->path, $b->path)));
        self::assertSame(0, $store->deepest(self::id('o2'), $ab->path));

        $abc->archive(new DateTimeImmutable('@3000'));
        $store->saveUnit($abc);
        self::assertSame(['a', 'ab-2', 'ab', 'abc', 'b'], self::unitIds($store->units(self::id('o1'))));
        self::assertSame(['a', 'ab-2', 'ab', 'b'], self::unitIds($store->units(self::id('o1'), null, false)));
        self::assertSame(['ab'], self::unitIds($store->units(self::id('o1'), $ab->path, false)));
        self::assertSame([], $store->children(self::id('o1'), $ab->id, false));
        self::assertSame(['abc'], self::unitIds($store->children(self::id('o1'), $ab->id)));
    }

    public function testASubtreeMovesWithItsPaths(): void
    {
        $store = $this->unitStore();
        [, $ab, , , $b] = $this->smallTree($store);

        $from = $ab->moveUnder($b);
        $store->moveUnit($ab, $from);

        self::assertSame('/b/ab/', self::found($store, 'ab')->path->toString());
        self::assertSame('b', self::found($store, 'ab')->parentId()?->value);
        self::assertSame('/b/ab/abc/', self::found($store, 'abc')->path->toString());
        self::assertSame('/a/ab-2/', self::found($store, 'ab-2')->path->toString());
        self::assertSame(['b', 'ab', 'abc'], self::unitIds($store->units(self::id('o1'), $b->path)));
        self::assertSame(['ab'], self::unitIds($store->children(self::id('o1'), $b->id)));
        self::assertSame(3, $store->deepest(self::id('o1'), $b->path));

        $from = $ab->moveUnder(null);
        $store->moveUnit($ab, $from);
        self::assertSame('/ab/abc/', self::found($store, 'abc')->path->toString());
        self::assertSame(['a', 'ab', 'b'], self::unitIds($store->children(self::id('o1'), null)));
        self::assertSame(2, $store->deepest(self::id('o1'), $ab->path));
    }

    public function testASubtreeIsArchivedAndRestoredTogether(): void
    {
        $store = $this->unitStore();
        [$a, $ab, $abc] = $this->smallTree($store);
        $abc->archive(new DateTimeImmutable('@2000'));
        $store->saveUnit($abc);

        self::assertSame(['a', 'ab-2', 'ab'], self::unitIds($store->archiveUnits(self::id('o1'), $a->path, new DateTimeImmutable('@3000'))));
        self::assertSame([], $store->archiveUnits(self::id('o1'), $a->path, new DateTimeImmutable('@4000')));
        self::assertSame([], $store->archiveUnits(self::id('o2'), $a->path, new DateTimeImmutable('@4000')));
        self::assertSame(3000, self::found($store, 'ab')->archivedAt?->getTimestamp());
        self::assertSame(2000, self::found($store, 'abc')->archivedAt?->getTimestamp());
        self::assertTrue(self::found($store, 'b')->isActive());

        self::assertSame([], $store->restoreUnits(self::id('o1'), $a->path, new DateTimeImmutable('@3001')));
        self::assertSame(['a', 'ab-2', 'ab'], self::unitIds($store->restoreUnits(self::id('o1'), $a->path, new DateTimeImmutable('@3000'))));
        self::assertTrue(self::found($store, 'ab')->isActive());
        self::assertSame(2000, self::found($store, 'abc')->archivedAt?->getTimestamp());
        self::assertSame(['abc'], self::unitIds($store->restoreUnits(self::id('o1'), $ab->path, new DateTimeImmutable('@2000'))));
    }

    public function testTheMembersOfUnits(): void
    {
        $store = $this->unitStore();
        $store->saveOrganization(self::organization('o1', 'acme'));
        $store->saveMembership(self::membership('o1', 'ann', ['owner']));
        $store->saveMembership(self::membership('o1', 'bob', ['member']));
        [$a, $ab, $abc, , $b] = $this->smallTree($store);
        $store->saveUnitMembership(self::unitMembership('abc', 'o1', 'bob', ['member']));
        $store->saveUnitMembership(self::unitMembership('ab', 'o1', 'ann', ['manager']));
        $store->saveUnitMembership(self::unitMembership('a', 'o1', 'bob', ['member']));
        $store->saveUnitMembership(self::unitMembership('b', 'o1', 'ann', ['member']));
        $store->saveUnitMembership(self::unitMembership('elsewhere', 'o2', 'ann', ['member']));

        self::assertEquals(self::unitMembership('ab', 'o1', 'ann', ['manager']), $store->unitMembership($ab->id, self::member('ann')));
        self::assertNull($store->unitMembership($ab->id, self::member('bob')));
        self::assertNull($store->unitMembership($ab->id, self::member('ANN')));

        $changed = self::unitMembership('ab', 'o1', 'ann', ['manager']);
        $changed->changeRoles(Roles::of(['manager', 'instructor']));

        $store->saveUnitMembership($changed);
        self::assertSame(['instructor', 'manager'], $store->unitMembership($ab->id, self::member('ann'))?->roles->names);
        self::assertSame(1000, $store->unitMembership($ab->id, self::member('ann'))->joinedAt->getTimestamp());

        $store->saveUnitMembership(self::unitMembership('ab', 'o1', 'cam', ['member']));
        self::assertSame(['ann', 'cam'], $this->unitMembers($store->unitMemberships($ab->id, 10)));
        self::assertSame(['ann'], $this->unitMembers($store->unitMemberships($ab->id, 1)));
        self::assertSame(['cam'], $this->unitMembers($store->unitMemberships($ab->id, 10, 'ann')));
        self::assertSame(['a', 'abc'], array_map(static fn (UnitMembership $m): string => $m->unitId->value, $store->unitMembershipsOf(self::member('bob'), self::id('o1'))));
        self::assertSame(['ab', 'b', 'elsewhere'], array_map(static fn (UnitMembership $m): string => $m->unitId->value, $store->unitMembershipsOf(self::member('ann'))));
        self::assertSame(['ab', 'b'], array_map(static fn (UnitMembership $m): string => $m->unitId->value, $store->unitMembershipsOf(self::member('ann'), self::id('o1'))));
        self::assertSame(['a', 'abc'], array_map(static fn (UnitMembership $m): string => $m->unitId->value, $store->unitMembershipsAmong($abc->path->unitIds(), self::member('bob'))));
        self::assertSame([], $store->unitMembershipsAmong([], self::member('bob')));
        self::assertSame(['ann', 'bob', 'cam'], $this->names($store->membersWithin(self::id('o1'), $a->path, 10)));
        self::assertSame(['ann', 'bob'], $this->names($store->membersWithin(self::id('o1'), $a->path, 2)));
        self::assertSame(['bob', 'cam'], $this->names($store->membersWithin(self::id('o1'), $a->path, 10, 'ann')));
        self::assertSame(['ann', 'bob', 'cam'], $this->names($store->membersWithin(self::id('o1'), $ab->path, 10)));
        self::assertSame([], $store->membersWithin(self::id('o2'), $ab->path, 10));

        self::assertTrue($store->deleteUnitMembership($ab->id, self::member('cam')));
        self::assertFalse($store->deleteUnitMembership($ab->id, self::member('cam')));

        self::assertTrue($store->deleteMembership(self::id('o1'), self::member('bob')));
        self::assertSame([], $store->unitMembershipsOf(self::member('bob')));
        self::assertSame(['ab', 'b', 'elsewhere'], array_map(static fn (UnitMembership $m): string => $m->unitId->value, $store->unitMembershipsOf(self::member('ann'))));
        self::assertSame(['ann'], $this->names($store->membersWithin(self::id('o1'), $b->path, 10)));
    }

    public function testAMemberNamedByDigitsIsAMemberOfUnits(): void
    {
        $store = $this->unitStore();
        [$a] = $this->smallTree($store);
        $store->saveUnitMembership(self::unitMembership('a', 'o1', '42', ['member']));
        $store->saveUnitMembership(self::unitMembership('ab', 'o1', '7', ['member']));

        self::assertSame(['42', '7'], $this->names($store->membersWithin(self::id('o1'), $a->path, 10)));
        self::assertSame(['7'], $this->names($store->membersWithin(self::id('o1'), $a->path, 10, '42')));
    }

    public function testUnitsGoInAndComeOutAsCopies(): void
    {
        $store = $this->unitStore();
        [$a, $ab, , , $b] = $this->smallTree($store);

        $a->rename(OrganizationName::fromString('Changed'));
        self::assertSame('A', self::found($store, 'a')->name->value);

        self::found($store, 'a')->rename(OrganizationName::fromString('Changed'));
        self::assertSame('A', self::found($store, 'a')->name->value);

        $store->units(self::id('o1'))[0]->rename(OrganizationName::fromString('Changed'));
        self::assertSame('A', self::found($store, 'a')->name->value);

        $store->children(self::id('o1'), null)[0]->rename(OrganizationName::fromString('Changed'));
        self::assertSame('A', self::found($store, 'a')->name->value);

        $store->unitsById([$a->id])[0]->rename(OrganizationName::fromString('Changed'));
        self::assertSame('A', self::found($store, 'a')->name->value);

        $ab->moveUnder($b);
        $store->saveUnit($ab);
        self::assertSame('/a/ab/', self::found($store, 'ab')->path->toString());
    }

    public function testUnitMembershipsGoInAndComeOutAsCopies(): void
    {
        $store = $this->unitStore();
        $membership = self::unitMembership('ab', 'o1', 'ann', ['member']);
        $store->saveUnitMembership($membership);
        $membership->changeRoles(Roles::of(['manager']));
        self::assertSame(['member'], $store->unitMembership(UnitId::fromString('ab'), self::member('ann'))?->roles->names);

        foreach ([
            $store->unitMembership(UnitId::fromString('ab'), self::member('ann')),
            $store->unitMemberships(UnitId::fromString('ab'), 10)[0],
            $store->unitMembershipsOf(self::member('ann'))[0],
            $store->unitMembershipsOf(self::member('ann'), self::id('o1'))[0],
            $store->unitMembershipsAmong([UnitId::fromString('ab')], self::member('ann'))[0],
        ] as $read) {
            self::assertNotNull($read);
            $read->changeRoles(Roles::of(['manager']));
        }

        self::assertSame(['member'], $store->unitMembership(UnitId::fromString('ab'), self::member('ann'))->roles->names);
    }

    public function testAMemberLeavesOneUnitAtATime(): void
    {
        $store = $this->unitStore();
        $this->smallTree($store);
        $store->saveUnitMembership(self::unitMembership('a', 'o1', 'ann', ['member']));
        $store->saveUnitMembership(self::unitMembership('b', 'o1', 'ann', ['member']));

        self::assertTrue($store->deleteUnitMembership(UnitId::fromString('a'), self::member('ann')));
        self::assertNotNull($store->unitMembership(UnitId::fromString('b'), self::member('ann')));
        self::assertFalse($store->deleteUnitMembership(UnitId::fromString('a'), self::member('ann')));
    }

    public function testAUnitsChangeThatThrowsIsRolledBack(): void
    {
        $store = $this->unitStore();
        $store->saveUnit(self::unit('a', 'o1', null));

        try {
            $store->transactional(static function () use ($store): void {
                $store->saveUnit(self::unit('b', 'o1', null));
                $store->saveUnitMembership(self::unitMembership('b', 'o1', 'ann', ['member']));

                throw new RuntimeException('Rolled back.');
            });
        } catch (RuntimeException) {
        }

        self::assertSame(['a'], self::unitIds($store->units(self::id('o1'))));
        self::assertSame([], $store->unitMembershipsOf(self::member('ann')));
    }

    protected static function found(UnitStore $store, string $id): Unit
    {
        return $store->unit(UnitId::fromString($id)) ?? self::fail(\sprintf('No unit "%s".', $id));
    }

    protected function unitStore(): OrganizationStore&UnitStore
    {
        $store = $this->store();
        self::assertInstanceOf(UnitStore::class, $store);

        return $store;
    }

    /**
     * Five units of `o1`: `a` with `ab` — itself with `abc` — and `ab-2`, then `b`.
     *
     * @return array{Unit, Unit, Unit, Unit, Unit}
     */
    protected function smallTree(UnitStore $store): array
    {
        $a = self::unit('a', 'o1', null);
        $ab = self::unit('ab', 'o1', $a);
        $abc = self::unit('abc', 'o1', $ab);
        $ab2 = self::unit('ab-2', 'o1', $a);
        $b = self::unit('b', 'o1', null);

        foreach ([$b, $abc, $a, $ab2, $ab] as $unit) {
            $store->saveUnit($unit);
        }

        return [$a, $ab, $abc, $ab2, $b];
    }

    protected static function unit(string $id, string $organization, ?Unit $parent): Unit
    {
        return Unit::create(UnitId::fromString($id), self::id($organization), OrganizationName::fromString(ucfirst($id)), $parent, new DateTimeImmutable('@1000'));
    }

    /**
     * @param list<string> $roles
     */
    protected static function unitMembership(string $unit, string $organization, string $member, array $roles): UnitMembership
    {
        return UnitMembership::join(UnitId::fromString($unit), self::id($organization), self::member($member), Roles::of($roles), new DateTimeImmutable('@1000'));
    }

    /**
     * @param list<Unit|UnitId> $units
     *
     * @return list<string>
     */
    protected static function unitIds(array $units): array
    {
        return array_map(static fn (Unit|UnitId $unit): string => $unit instanceof Unit ? $unit->id->value : $unit->value, $units);
    }

    /**
     * @param list<UnitMembership> $memberships
     *
     * @return list<string>
     */
    private function unitMembers(array $memberships): array
    {
        return array_map(static fn (UnitMembership $m): string => $m->memberId->value, $memberships);
    }

    /**
     * @param list<MemberId> $members
     *
     * @return list<string>
     */
    private function names(array $members): array
    {
        return array_map(static fn (MemberId $member): string => $member->value, $members);
    }

    private static function rollBack(OrganizationStore $store): string
    {
        $store->saveOrganization(self::organization('o2', 'other'));
        $store->saveMembership(self::membership('o1', 'bob', ['owner']));
        $store->addInvitation(self::invitation('i1', 'o1', 1, 1000, 2000), '$hash$1');

        throw new RuntimeException('Rolled back.');
    }

    protected static function organization(string $id, string $slug, string $name = 'Acme'): Organization
    {
        return Organization::create(self::id($id), OrganizationName::fromString($name), Slug::fromString($slug), new DateTimeImmutable('@1000'));
    }

    /**
     * @param list<string> $roles
     */
    protected static function membership(string $organization, string $member, array $roles, JoinMethod $method = JoinMethod::Added): Membership
    {
        return Membership::join(self::id($organization), self::member($member), Roles::of($roles), $method, new DateTimeImmutable('@1000'));
    }

    protected static function team(string $id, string $organization, string $slug): Team
    {
        return Team::create(TeamId::fromString($id), self::id($organization), OrganizationName::fromString(ucfirst($slug)), Slug::fromString($slug), new DateTimeImmutable('@1000'));
    }

    /**
     * @param list<string> $roles
     */
    protected static function teamMembership(string $team, string $member, array $roles): TeamMembership
    {
        return TeamMembership::join(TeamId::fromString($team), self::member($member), Roles::of($roles), new DateTimeImmutable('@1000'));
    }

    protected static function invitation(string $id, string $organization, int $uses, int $createdAt, int $expiresAt, string $creator = 'ann'): Invitation
    {
        return Invitation::create(InvitationId::fromString($id), self::id($organization), Roles::of(['member']), $uses, new DateTimeImmutable('@'.$createdAt), new DateTimeImmutable('@'.$expiresAt), self::member($creator));
    }

    protected static function id(string $id): OrganizationId
    {
        return OrganizationId::fromString($id);
    }

    protected static function member(string $id): MemberId
    {
        return MemberId::fromString($id);
    }

    /**
     * @param list<Organization> $organizations
     *
     * @return list<string>
     */
    private function slugs(array $organizations): array
    {
        return array_map(static fn (Organization $o): string => $o->slug->value, $organizations);
    }

    /**
     * @param list<Membership> $memberships
     *
     * @return list<string>
     */
    private function members(array $memberships): array
    {
        return array_map(static fn (Membership $m): string => $m->memberId->value, $memberships);
    }

    /**
     * @param list<TeamMembership> $memberships
     *
     * @return list<string>
     */
    private function teamsOf(array $memberships): array
    {
        return array_map(static fn (TeamMembership $m): string => $m->teamId->value, $memberships);
    }

    /**
     * @param list<Invitation> $invitations
     *
     * @return list<string>
     */
    private function invitationIds(array $invitations): array
    {
        return array_map(static fn (Invitation $i): string => $i->id->value, $invitations);
    }
}
