<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\MemberErasure;
use Phil\OrganizationsBundle\Application\UnitOfWork;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\TeamMembership;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\OrganizationsBundle\Infrastructure\Privacy\OrganizationsPrivacy;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(OrganizationsPrivacy::class)]
final class PrivacyTest extends TestCase
{
    public function testExportAndErase(): void
    {
        $stage = new Stage();
        $acme = $stage->acme();
        $team = $stage->teams->create($acme->id, Stage::member('ann'), 'Design');
        $stage->teams->addMember($team->id, Stage::member('ann'), Stage::member('bob'), ['maintainer']);
        $issued = $stage->invitations->create($acme->id, Stage::member('bob'), ['member'], null, 3);
        $privacy = new OrganizationsPrivacy($stage->store, $stage->erasure);
        $at = $stage->clock->now()->format(\DATE_ATOM);

        self::assertSame('organizations', $privacy->module()->value);
        self::assertSame([
            'memberships' => [['organization' => $acme->id->value, 'name' => 'Acme Guild', 'roles' => ['admin'], 'joined_by' => 'added', 'joined_at' => $at]],
            'teams' => [['team' => $team->id->value, 'name' => 'Design', 'roles' => ['maintainer'], 'joined_at' => $at]],
            'invitations_created' => [['organization' => $acme->id->value, 'roles' => ['member'], 'uses' => 0, 'created_at' => $at, 'expires_at' => $issued->invitation->expiresAt->format(\DATE_ATOM)]],
        ], $privacy->export(DataSubject::fromString('bob')));
        self::assertSame(['memberships'], array_keys($privacy->export(DataSubject::fromString('cam'))));
        self::assertSame([], $privacy->export(DataSubject::fromString('zed')));
        self::assertSame([], $privacy->export(DataSubject::fromString('bob@example.org')));

        $report = $privacy->erase(DataSubject::fromString('bob'));

        self::assertSame('organizations', $report->module->value);
        self::assertSame([
            'phil_organization_member' => ErasureEffect::Deleted,
            'phil_organization_member_role' => ErasureEffect::Deleted,
            'phil_organization_team_member' => ErasureEffect::Deleted,
            'phil_organization_invitation' => ErasureEffect::Pseudonymised,
        ], $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('bob')));
        self::assertNull($stage->store->invitation($issued->invitation->id)?->createdBy);
        self::assertEquals($report, $privacy->erase(DataSubject::fromString('bob')));
        self::assertEquals($report, $privacy->erase(DataSubject::fromString('bob@example.org')));
        self::assertNotNull($stage->members->membership($acme->id, Stage::member('cam')));
    }

    public function testAMembershipWhoseOrganizationOrTeamIsGoneIsExportedWithoutAName(): void
    {
        $joined = new DateTimeImmutable('@1000');
        $store = self::createStub(OrganizationStore::class);
        $store->method('membershipsOf')->willReturn([Membership::restored(OrganizationId::fromString('o1'), MemberId::fromString('bob'), Roles::of(['member']), JoinMethod::Added, $joined)]);
        $store->method('teamMembershipsOf')->willReturn([TeamMembership::restored(TeamId::fromString('t1'), MemberId::fromString('bob'), Roles::of(['member']), $joined)]);
        $privacy = new OrganizationsPrivacy($store, new MemberErasure($store, new UnitOfWork($store), new MockClock()));

        $export = $privacy->export(DataSubject::fromString('bob'));

        self::assertSame([
            'memberships' => [['organization' => 'o1', 'name' => null, 'roles' => ['member'], 'joined_by' => 'added', 'joined_at' => '1970-01-01T00:16:40+00:00']],
            'teams' => [['team' => 't1', 'name' => null, 'roles' => ['member'], 'joined_at' => '1970-01-01T00:16:40+00:00']],
        ], $export);
    }
}
