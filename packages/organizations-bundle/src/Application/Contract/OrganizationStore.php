<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Contract;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Application\Exception\SlugTaken;
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
use Phil\Slug\Slug;

/**
 * Where organizations, their members, teams and invitations are kept: the tables of a Doctrine DBAL connection, or
 * memory for tests. Every change of the bundle runs inside `transactional()`, and reads the organization it changes
 * with `$lock`, so that two changes of the same organization — two owners demoting each other — run one after the
 * other and the rule of the last owner holds.
 */
interface OrganizationStore
{
    public const int MAX_PAGE = 1000;

    /**
     * Runs the work in one transaction: committed when it returns, rolled back when it throws.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function transactional(callable $work): mixed;

    /**
     * @param bool $lock whether to lock the organization until the transaction ends
     */
    public function organization(OrganizationId $id, bool $lock = false): ?Organization;

    public function organizationBySlug(Slug $slug): ?Organization;

    /**
     * Adds the organization, or writes its changes.
     *
     * @throws SlugTaken when another organization has its slug
     */
    public function saveOrganization(Organization $organization): void;

    /**
     * @param int<1, 1000> $limit
     *
     * @return list<Organization> sorted by slug, after `$afterSlug` when it is given
     */
    public function organizations(?OrganizationStatus $status, int $limit, ?string $afterSlug = null): array;

    /**
     * @return list<Organization> the active organizations that verified the domain, sorted by slug
     */
    public function organizationsWithDomain(EmailDomain $domain): array;

    public function membership(OrganizationId $organizationId, MemberId $memberId): ?Membership;

    /**
     * @param int<1, 1000> $limit
     *
     * @return list<Membership> sorted by member, after `$afterMember` when it is given
     */
    public function memberships(OrganizationId $organizationId, int $limit, ?string $afterMember = null): array;

    /**
     * @return list<Membership> every membership of the member, sorted by organization
     */
    public function membershipsOf(MemberId $memberId): array;

    /**
     * Adds the membership, or writes its roles.
     */
    public function saveMembership(Membership $membership): void;

    /**
     * Deletes the membership and the member's memberships of the organization's teams, and of its units when the store
     * keeps units too.
     */
    public function deleteMembership(OrganizationId $organizationId, MemberId $memberId): bool;

    public function countMembers(OrganizationId $organizationId): int;

    public function countWithRole(OrganizationId $organizationId, string $role): int;

    /**
     * @param int<1, 1000> $limit
     *
     * @return array<string, int> the organizations where fewer than `$below` members hold `$role`, by identifier,
     *                            with how many do, the fewest first
     */
    public function organizationsWithFewer(string $role, int $below, int $limit): array;

    public function team(TeamId $id): ?Team;

    public function teamBySlug(OrganizationId $organizationId, Slug $slug): ?Team;

    /**
     * @return list<Team> sorted by slug
     */
    public function teams(OrganizationId $organizationId): array;

    /**
     * Adds the team, or writes its changes.
     *
     * @throws SlugTaken when another team of the organization has its slug
     */
    public function saveTeam(Team $team): void;

    /**
     * Deletes the team and its memberships.
     */
    public function deleteTeam(TeamId $id): bool;

    public function teamMembership(TeamId $teamId, MemberId $memberId): ?TeamMembership;

    /**
     * @return list<TeamMembership> sorted by member
     */
    public function teamMemberships(TeamId $teamId): array;

    /**
     * @return list<TeamMembership> the member's team memberships, of one organization when it is given, sorted by team
     */
    public function teamMembershipsOf(MemberId $memberId, ?OrganizationId $organizationId = null): array;

    /**
     * Adds the team membership, or writes its roles.
     */
    public function saveTeamMembership(TeamMembership $membership): void;

    public function deleteTeamMembership(TeamId $teamId, MemberId $memberId): bool;

    /**
     * @param non-empty-string $tokenHash the keyed hash of its token, never the token
     */
    public function addInvitation(Invitation $invitation, string $tokenHash): void;

    public function invitation(InvitationId $id): ?Invitation;

    /**
     * @param non-empty-list<string> $hashes the hashes of a token under each key
     */
    public function invitationByHash(array $hashes): ?Invitation;

    /**
     * @return list<Invitation> the organization's invitations, the newest first
     */
    public function invitations(OrganizationId $organizationId): array;

    /**
     * Counts one use of the invitation, in one conditional write: only while it is neither revoked, used up nor
     * expired at `$now`.
     *
     * @return bool whether this call counted the use
     */
    public function useInvitation(InvitationId $id, DateTimeImmutable $now): bool;

    /**
     * @return bool whether this call revoked it
     */
    public function revokeInvitation(InvitationId $id, DateTimeImmutable $now): bool;

    /**
     * Deletes the invitations that expired or were revoked at `$before` or earlier.
     *
     * @return int how many
     */
    public function purgeInvitations(DateTimeImmutable $before): int;

    /**
     * @return list<Invitation> the invitations the member created, the newest first
     */
    public function invitationsCreatedBy(MemberId $memberId): array;

    /**
     * Forgets the member as the creator of their invitations.
     *
     * @return int how many invitations
     */
    public function forgetInvitationCreator(MemberId $memberId): int;
}
