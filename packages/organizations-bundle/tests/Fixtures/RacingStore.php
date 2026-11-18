<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Fixtures;

use DateTimeImmutable;
use Override;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Domain\Model\Invitation;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Team;
use Phil\OrganizationsBundle\Domain\Model\TeamMembership;
use Phil\OrganizationsBundle\Domain\Status\OrganizationStatus;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\OrganizationsBundle\Infrastructure\Memory\InMemoryOrganizationStore;
use Phil\Slug\Slug;

/**
 * A store in memory whose writes can be made to lose a race: `$loseUses` makes every use of an invitation fail, as
 * when another member took its last use between the read and the write; `$locks` counts the organizations read with a
 * lock; the saves of organizations and teams are counted too.
 */
final class RacingStore implements OrganizationStore
{
    public bool $loseUses = false;

    public int $locks = 0;

    public int $organizationSaves = 0;

    public int $teamSaves = 0;

    public function __construct(
        private readonly InMemoryOrganizationStore $inner = new InMemoryOrganizationStore(),
    ) {
    }

    #[Override]
    public function transactional(callable $work): mixed
    {
        return $this->inner->transactional($work);
    }

    #[Override]
    public function organization(OrganizationId $id, bool $lock = false): ?Organization
    {
        $this->locks += (int) $lock;

        return $this->inner->organization($id, $lock);
    }

    #[Override]
    public function organizationBySlug(Slug $slug): ?Organization
    {
        return $this->inner->organizationBySlug($slug);
    }

    #[Override]
    public function saveOrganization(Organization $organization): void
    {
        ++$this->organizationSaves;
        $this->inner->saveOrganization($organization);
    }

    #[Override]
    public function organizations(?OrganizationStatus $status, int $limit, ?string $afterSlug = null): array
    {
        return $this->inner->organizations($status, $limit, $afterSlug);
    }

    #[Override]
    public function organizationsWithDomain(EmailDomain $domain): array
    {
        return $this->inner->organizationsWithDomain($domain);
    }

    #[Override]
    public function membership(OrganizationId $organizationId, MemberId $memberId): ?Membership
    {
        return $this->inner->membership($organizationId, $memberId);
    }

    #[Override]
    public function memberships(OrganizationId $organizationId, int $limit, ?string $afterMember = null): array
    {
        return $this->inner->memberships($organizationId, $limit, $afterMember);
    }

    #[Override]
    public function membershipsOf(MemberId $memberId): array
    {
        return $this->inner->membershipsOf($memberId);
    }

    #[Override]
    public function saveMembership(Membership $membership): void
    {
        $this->inner->saveMembership($membership);
    }

    #[Override]
    public function deleteMembership(OrganizationId $organizationId, MemberId $memberId): bool
    {
        return $this->inner->deleteMembership($organizationId, $memberId);
    }

    #[Override]
    public function countMembers(OrganizationId $organizationId): int
    {
        return $this->inner->countMembers($organizationId);
    }

    #[Override]
    public function countWithRole(OrganizationId $organizationId, string $role): int
    {
        return $this->inner->countWithRole($organizationId, $role);
    }

    #[Override]
    public function organizationsWithFewer(string $role, int $below, int $limit): array
    {
        return $this->inner->organizationsWithFewer($role, $below, $limit);
    }

    #[Override]
    public function team(TeamId $id): ?Team
    {
        return $this->inner->team($id);
    }

    #[Override]
    public function teamBySlug(OrganizationId $organizationId, Slug $slug): ?Team
    {
        return $this->inner->teamBySlug($organizationId, $slug);
    }

    #[Override]
    public function teams(OrganizationId $organizationId): array
    {
        return $this->inner->teams($organizationId);
    }

    #[Override]
    public function saveTeam(Team $team): void
    {
        ++$this->teamSaves;
        $this->inner->saveTeam($team);
    }

    #[Override]
    public function deleteTeam(TeamId $id): bool
    {
        return $this->inner->deleteTeam($id);
    }

    #[Override]
    public function teamMembership(TeamId $teamId, MemberId $memberId): ?TeamMembership
    {
        return $this->inner->teamMembership($teamId, $memberId);
    }

    #[Override]
    public function teamMemberships(TeamId $teamId): array
    {
        return $this->inner->teamMemberships($teamId);
    }

    #[Override]
    public function teamMembershipsOf(MemberId $memberId, ?OrganizationId $organizationId = null): array
    {
        return $this->inner->teamMembershipsOf($memberId, $organizationId);
    }

    #[Override]
    public function saveTeamMembership(TeamMembership $membership): void
    {
        $this->inner->saveTeamMembership($membership);
    }

    #[Override]
    public function deleteTeamMembership(TeamId $teamId, MemberId $memberId): bool
    {
        return $this->inner->deleteTeamMembership($teamId, $memberId);
    }

    #[Override]
    public function addInvitation(Invitation $invitation, string $tokenHash): void
    {
        $this->inner->addInvitation($invitation, $tokenHash);
    }

    #[Override]
    public function invitation(InvitationId $id): ?Invitation
    {
        return $this->inner->invitation($id);
    }

    #[Override]
    public function invitationByHash(array $hashes): ?Invitation
    {
        return $this->inner->invitationByHash($hashes);
    }

    #[Override]
    public function invitations(OrganizationId $organizationId): array
    {
        return $this->inner->invitations($organizationId);
    }

    #[Override]
    public function useInvitation(InvitationId $id, DateTimeImmutable $now): bool
    {
        return !$this->loseUses && $this->inner->useInvitation($id, $now);
    }

    #[Override]
    public function revokeInvitation(InvitationId $id, DateTimeImmutable $now): bool
    {
        return $this->inner->revokeInvitation($id, $now);
    }

    #[Override]
    public function purgeInvitations(DateTimeImmutable $before): int
    {
        return $this->inner->purgeInvitations($before);
    }

    #[Override]
    public function invitationsCreatedBy(MemberId $memberId): array
    {
        return $this->inner->invitationsCreatedBy($memberId);
    }

    #[Override]
    public function forgetInvitationCreator(MemberId $memberId): int
    {
        return $this->inner->forgetInvitationCreator($memberId);
    }
}
