<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Infrastructure;

use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\OrganizationsBundle\Infrastructure\Security\OrganizationVoter;
use Phil\OrganizationsBundle\Infrastructure\Security\UserIdentifierMember;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(OrganizationVoter::class)]
#[CoversClass(UserIdentifierMember::class)]
final class SecurityTest extends TestCase
{
    private Organization $acme;

    private OrganizationVoter $voter;

    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->acme = $this->stage->acme();
        $this->voter = new OrganizationVoter($this->stage->access, new UserIdentifierMember(), $this->stage->unitAccess);
    }

    public function testPermissionsAndRolesInAUnitHoldBelowIt(): void
    {
        $sales = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'Sales');
        $east = $this->stage->units->create($this->acme->id, Stage::member('ann'), 'East', $sales->id);
        $this->stage->units->addMember($sales->id, Stage::member('ann'), Stage::member('cam'), ['manager']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote('cam', 'unit:unit.manage', $east));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote('cam', 'unit:unit.manage', $east->id));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote('cam', 'unit:unit.manage', $east->id->value));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote('cam', 'unit_role:manager', $east));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote('cam', 'unit_role:member', $east));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote('bob', 'unit:unit.manage', $east));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote('bob', 'unit_role:manager', $east));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote('zed', 'unit:unit.manage', $east));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote('cam', 'unit:unit.manage', 'not an id'));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote('cam', 'unit:unit.manage', 'nowhere'));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote(new NullToken(), $east, ['unit:unit.manage']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote('cam', 'unit:unit.manage', $this->acme));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote('cam', 'unit:unit.manage', 12));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote('ann', 'organization:organization.view', $east));
        self::assertTrue($this->voter->supportsAttribute('unit:unit.manage'));
        self::assertTrue($this->voter->supportsAttribute('unit_role:manager'));
        self::assertFalse($this->voter->supportsAttribute('xunit:unit.manage'));
        self::assertTrue($this->voter->supportsType(Unit::class));
        self::assertTrue($this->voter->supportsType(UnitId::class));

        $vote = new Vote();
        $this->voter->vote(new UsernamePasswordToken(new InMemoryUser('cam', null), 'main'), $east, ['unit_role:manager'], $vote);
        self::assertSame(['The member holds "unit_role:manager" in the unit.'], $vote->reasons);
        $vote = new Vote();
        $this->voter->vote(new UsernamePasswordToken(new InMemoryUser('cam', null), 'main'), $east, ['unit:courses.assign'], $vote);
        self::assertSame(['The member does not hold "unit:courses.assign" in the unit.'], $vote->reasons);
        $vote = new Vote();
        $this->voter->vote(new NullToken(), $east, ['unit:unit.manage'], $vote);
        self::assertSame(['Not a member of a known unit.'], $vote->reasons);

        $withoutUnits = new OrganizationVoter($this->stage->access, new UserIdentifierMember());
        self::assertSame(VoterInterface::ACCESS_DENIED, $withoutUnits->vote(new UsernamePasswordToken(new InMemoryUser('cam', null), 'main'), $east, ['unit:unit.manage']));
    }

    public function testPermissionsAndRolesOfAMember(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote('bob', 'organization:members.invite', $this->acme));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote('bob', 'organization:members.invite', $this->acme->id));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote('bob', 'organization:members.invite', $this->acme->id->value));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote('cam', 'organization:members.invite', $this->acme));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote('ann', 'organization_role:owner', $this->acme));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote('bob', 'organization_role:owner', $this->acme));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote('zed', 'organization:organization.view', $this->acme));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote('ann', 'organization:organization.view', 'not an id'));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote('ann@example.org', 'organization:organization.view', $this->acme));
    }

    public function testTheReasonsOfAVote(): void
    {
        $vote = new Vote();
        $this->voter->vote(new UsernamePasswordToken(new InMemoryUser('bob', null), 'main'), $this->acme, ['organization_role:owner'], $vote);
        self::assertSame(['The member does not hold "organization_role:owner" in the organization.'], $vote->reasons);

        $vote = new Vote();
        $this->voter->vote(new UsernamePasswordToken(new InMemoryUser('ann', null), 'main'), $this->acme, ['organization:members.invite'], $vote);
        self::assertSame(['The member holds "organization:members.invite" in the organization.'], $vote->reasons);

        $vote = new Vote();
        $this->voter->vote(new NullToken(), $this->acme, ['organization:members.invite'], $vote);
        self::assertSame(['Not a member of a known organization.'], $vote->reasons);
    }

    public function testWhatTheVoterSupports(): void
    {
        self::assertTrue($this->voter->supportsAttribute('organization:members.invite'));
        self::assertTrue($this->voter->supportsAttribute('organization_role:owner'));
        self::assertFalse($this->voter->supportsAttribute('ROLE_ADMIN'));
        self::assertFalse($this->voter->supportsAttribute('xorganization:members.invite'));
        self::assertTrue($this->voter->supportsType(Organization::class));
        self::assertTrue($this->voter->supportsType('string'));
        self::assertTrue($this->voter->supportsType(OrganizationId::class));
        self::assertFalse($this->voter->supportsType('int'));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote('ann', 'ROLE_ADMIN', $this->acme));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote('ann', 'organization:organization.view', 12));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote(new NullToken(), $this->acme, ['organization:organization.view']));
    }

    public function testAUserIdentifiedByAnAddressIsNoMember(): void
    {
        $members = new UserIdentifierMember();

        self::assertSame('ann', $members->memberId(new InMemoryUser('ann', null))?->value);
        self::assertNull($members->memberId(new InMemoryUser('ann@example.org', null)));
    }

    private function vote(string $user, string $attribute, mixed $subject): int
    {
        return $this->voter->vote(new UsernamePasswordToken(new InMemoryUser($user, null), 'main'), $subject, [$attribute]);
    }
}
