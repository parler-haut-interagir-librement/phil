<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
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
use Phil\OrganizationsBundle\Domain\Status\InvitationState;
use Phil\OrganizationsBundle\Domain\Status\OrganizationStatus;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitPath;
use Phil\Slug\Slug;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/**
 * The organizations and their units in memory, for tests and scripts: copies go in and come out, as with a database, and a
 * transaction that throws puts everything back as it was. `reset()` forgets everything, between the requests of a
 * worker or the tests.
 */
final class InMemoryOrganizationStore implements OrganizationStore, UnitStore, ResetInterface
{
    /** @var array<string, Organization> */
    private array $organizations = [];

    /** @var array<string, array<string, Membership>> by organization, then member */
    private array $memberships = [];

    /** @var array<string, Team> */
    private array $teams = [];

    /** @var array<string, array<string, TeamMembership>> by team, then member */
    private array $teamMemberships = [];

    /** @var array<string, Invitation> */
    private array $invitations = [];

    /** @var array<string, string> invitation identifiers by token hash */
    private array $hashes = [];

    /** @var array<string, Unit> */
    private array $units = [];

    /** @var array<string, array<string, UnitMembership>> by unit, then member */
    private array $unitMemberships = [];

    #[Override]
    public function transactional(callable $work): mixed
    {
        $before = $this->snapshot();

        try {
            return $work();
        } catch (Throwable $throwable) {
            [$this->organizations, $this->memberships, $this->teams, $this->teamMemberships, $this->invitations, $this->hashes, $this->units, $this->unitMemberships] = $before;

            throw $throwable;
        }
    }

    #[Override]
    public function organization(OrganizationId $id, bool $lock = false): ?Organization
    {
        return $this->copy($this->organizations[$id->value] ?? null);
    }

    #[Override]
    public function organizationBySlug(Slug $slug): ?Organization
    {
        foreach ($this->organizations as $organization) {
            if ($organization->slug->equals($slug)) {
                return clone $organization;
            }
        }

        return null;
    }

    #[Override]
    public function saveOrganization(Organization $organization): void
    {
        $other = $this->organizationBySlug($organization->slug);

        if ($other instanceof Organization && !$other->id->equals($organization->id)) {
            throw SlugTaken::of($organization->slug->value);
        }

        $this->organizations[$organization->id->value] = clone $organization;
    }

    #[Override]
    public function organizations(?OrganizationStatus $status, int $limit, ?string $afterSlug = null): array
    {
        $found = array_filter(
            $this->organizations,
            static fn (Organization $organization): bool => (!$status instanceof OrganizationStatus || $status === $organization->status) && (null === $afterSlug || strcmp($organization->slug->value, $afterSlug) > 0),
        );
        usort($found, static fn (Organization $a, Organization $b): int => strcmp($a->slug->value, $b->slug->value));

        return array_map(static fn (Organization $organization): Organization => clone $organization, \array_slice($found, 0, $limit));
    }

    #[Override]
    public function organizationsWithDomain(EmailDomain $domain): array
    {
        $found = array_filter($this->organizations, static fn (Organization $organization): bool => $organization->isActive() && (bool) $organization->emailDomain?->equals($domain));
        usort($found, static fn (Organization $a, Organization $b): int => strcmp($a->slug->value, $b->slug->value));

        return array_map(static fn (Organization $organization): Organization => clone $organization, $found);
    }

    #[Override]
    public function membership(OrganizationId $organizationId, MemberId $memberId): ?Membership
    {
        return $this->copy($this->memberships[$organizationId->value][$memberId->value] ?? null);
    }

    #[Override]
    public function memberships(OrganizationId $organizationId, int $limit, ?string $afterMember = null): array
    {
        $found = array_filter($this->memberships[$organizationId->value] ?? [], static fn (Membership $membership): bool => null === $afterMember || strcmp($membership->memberId->value, $afterMember) > 0);
        usort($found, static fn (Membership $a, Membership $b): int => strcmp($a->memberId->value, $b->memberId->value));

        return array_map(static fn (Membership $membership): Membership => clone $membership, \array_slice($found, 0, $limit));
    }

    #[Override]
    public function membershipsOf(MemberId $memberId): array
    {
        $found = [];

        foreach ($this->memberships as $members) {
            if (isset($members[$memberId->value])) {
                $found[] = clone $members[$memberId->value];
            }
        }

        usort($found, static fn (Membership $a, Membership $b): int => strcmp($a->organizationId->value, $b->organizationId->value));

        return $found;
    }

    #[Override]
    public function saveMembership(Membership $membership): void
    {
        $this->memberships[$membership->organizationId->value][$membership->memberId->value] = clone $membership;
    }

    #[Override]
    public function deleteMembership(OrganizationId $organizationId, MemberId $memberId): bool
    {
        foreach ($this->unitMembershipsOf($memberId, $organizationId) as $unitMembership) {
            unset($this->unitMemberships[$unitMembership->unitId->value][$memberId->value]);
        }

        foreach ($this->teamMembershipsOf($memberId, $organizationId) as $teamMembership) {
            unset($this->teamMemberships[$teamMembership->teamId->value][$memberId->value]);
        }

        $found = isset($this->memberships[$organizationId->value][$memberId->value]);
        unset($this->memberships[$organizationId->value][$memberId->value]);

        return $found;
    }

    #[Override]
    public function countMembers(OrganizationId $organizationId): int
    {
        return \count($this->memberships[$organizationId->value] ?? []);
    }

    #[Override]
    public function countWithRole(OrganizationId $organizationId, string $role): int
    {
        return \count(array_filter($this->memberships[$organizationId->value] ?? [], static fn (Membership $membership): bool => $membership->roles->has($role)));
    }

    #[Override]
    public function organizationsWithFewer(string $role, int $below, int $limit): array
    {
        $found = [];

        foreach (array_keys($this->organizations) as $id) {
            $holders = $this->countWithRole(OrganizationId::fromString($id), $role);

            if ($holders < $below) {
                $found[$id] = $holders;
            }
        }

        uksort($found, static fn (string $a, string $b): int => [$found[$a], $a] <=> [$found[$b], $b]);

        return \array_slice($found, 0, $limit, true);
    }

    #[Override]
    public function team(TeamId $id): ?Team
    {
        return $this->copy($this->teams[$id->value] ?? null);
    }

    #[Override]
    public function teamBySlug(OrganizationId $organizationId, Slug $slug): ?Team
    {
        foreach ($this->teams as $team) {
            if ($team->organizationId->equals($organizationId) && $team->slug->equals($slug)) {
                return clone $team;
            }
        }

        return null;
    }

    #[Override]
    public function teams(OrganizationId $organizationId): array
    {
        $found = array_filter($this->teams, static fn (Team $team): bool => $team->organizationId->equals($organizationId));
        usort($found, static fn (Team $a, Team $b): int => strcmp($a->slug->value, $b->slug->value));

        return array_map(static fn (Team $team): Team => clone $team, $found);
    }

    #[Override]
    public function saveTeam(Team $team): void
    {
        $other = $this->teamBySlug($team->organizationId, $team->slug);

        if ($other instanceof Team && !$other->id->equals($team->id)) {
            throw SlugTaken::of($team->slug->value);
        }

        $this->teams[$team->id->value] = clone $team;
    }

    #[Override]
    public function deleteTeam(TeamId $id): bool
    {
        $found = isset($this->teams[$id->value]);
        unset($this->teams[$id->value], $this->teamMemberships[$id->value]);

        return $found;
    }

    #[Override]
    public function teamMembership(TeamId $teamId, MemberId $memberId): ?TeamMembership
    {
        return $this->copy($this->teamMemberships[$teamId->value][$memberId->value] ?? null);
    }

    #[Override]
    public function teamMemberships(TeamId $teamId): array
    {
        $found = array_values($this->teamMemberships[$teamId->value] ?? []);
        usort($found, static fn (TeamMembership $a, TeamMembership $b): int => strcmp($a->memberId->value, $b->memberId->value));

        return array_map(static fn (TeamMembership $membership): TeamMembership => clone $membership, $found);
    }

    #[Override]
    public function teamMembershipsOf(MemberId $memberId, ?OrganizationId $organizationId = null): array
    {
        $found = [];

        foreach ($this->teamMemberships as $teamId => $members) {
            $team = $this->teams[$teamId] ?? null;

            if (isset($members[$memberId->value]) && $team instanceof Team && (!$organizationId instanceof OrganizationId || $team->organizationId->equals($organizationId))) {
                $found[] = clone $members[$memberId->value];
            }
        }

        usort($found, static fn (TeamMembership $a, TeamMembership $b): int => strcmp($a->teamId->value, $b->teamId->value));

        return $found;
    }

    #[Override]
    public function saveTeamMembership(TeamMembership $membership): void
    {
        $this->teamMemberships[$membership->teamId->value][$membership->memberId->value] = clone $membership;
    }

    #[Override]
    public function deleteTeamMembership(TeamId $teamId, MemberId $memberId): bool
    {
        $found = isset($this->teamMemberships[$teamId->value][$memberId->value]);
        unset($this->teamMemberships[$teamId->value][$memberId->value]);

        return $found;
    }

    #[Override]
    public function addInvitation(Invitation $invitation, string $tokenHash): void
    {
        $this->invitations[$invitation->id->value] = clone $invitation;
        $this->hashes[$tokenHash] = $invitation->id->value;
    }

    #[Override]
    public function invitation(InvitationId $id): ?Invitation
    {
        return $this->copy($this->invitations[$id->value] ?? null);
    }

    #[Override]
    public function invitationByHash(array $hashes): ?Invitation
    {
        foreach ($hashes as $hash) {
            $invitation = $this->invitations[$this->hashes[$hash] ?? ''] ?? null;

            if ($invitation instanceof Invitation) {
                return clone $invitation;
            }
        }

        return null;
    }

    #[Override]
    public function invitations(OrganizationId $organizationId): array
    {
        return $this->newestFirst(array_filter($this->invitations, static fn (Invitation $invitation): bool => $invitation->organizationId->equals($organizationId)));
    }

    #[Override]
    public function useInvitation(InvitationId $id, DateTimeImmutable $now): bool
    {
        $invitation = $this->invitations[$id->value] ?? null;

        if (!$invitation instanceof Invitation || InvitationState::Usable !== $invitation->stateAt($now)) {
            return false;
        }

        $invitation->use();

        return true;
    }

    #[Override]
    public function revokeInvitation(InvitationId $id, DateTimeImmutable $now): bool
    {
        $invitation = $this->invitations[$id->value] ?? null;

        if (!$invitation instanceof Invitation || $invitation->revokedAt instanceof DateTimeImmutable) {
            return false;
        }

        $invitation->revoke($now);

        return true;
    }

    #[Override]
    public function purgeInvitations(DateTimeImmutable $before): int
    {
        $purged = 0;

        foreach ($this->invitations as $id => $invitation) {
            $revoked = $invitation->revokedAt instanceof DateTimeImmutable && $invitation->revokedAt->getTimestamp() <= $before->getTimestamp();

            if ($revoked || $invitation->expiresAt->getTimestamp() <= $before->getTimestamp()) {
                unset($this->invitations[$id]);
                $this->hashes = array_filter($this->hashes, static fn (string $invitationId): bool => $invitationId !== $id);
                ++$purged;
            }
        }

        return $purged;
    }

    #[Override]
    public function invitationsCreatedBy(MemberId $memberId): array
    {
        return $this->newestFirst(array_filter($this->invitations, static fn (Invitation $invitation): bool => (bool) $invitation->createdBy?->equals($memberId)));
    }

    #[Override]
    public function forgetInvitationCreator(MemberId $memberId): int
    {
        $forgotten = 0;

        foreach ($this->invitations as $invitation) {
            if (true === $invitation->createdBy?->equals($memberId)) {
                $invitation->forgetCreator();
                ++$forgotten;
            }
        }

        return $forgotten;
    }

    #[Override]
    public function unit(UnitId $id): ?Unit
    {
        return $this->copy($this->units[$id->value] ?? null);
    }

    #[Override]
    public function saveUnit(Unit $unit): void
    {
        $stored = $this->units[$unit->id->value] ?? null;

        if ($stored instanceof Unit && !$stored->path->equals($unit->path)) {
            $unit = Unit::restored($unit->id, $unit->organizationId, $unit->name, $stored->path, $unit->createdAt, $unit->archivedAt);
        }

        $this->units[$unit->id->value] = clone $unit;
    }

    #[Override]
    public function moveUnit(Unit $unit, UnitPath $from): void
    {
        foreach ($this->units as $id => $stored) {
            if ($stored->organizationId->equals($unit->organizationId) && $stored->path->isWithin($from)) {
                $this->units[$id] = Unit::restored($stored->id, $stored->organizationId, $stored->name, $stored->path->rebase($from, $unit->path), $stored->createdAt, $stored->archivedAt);
            }
        }
    }

    #[Override]
    public function units(OrganizationId $organizationId, ?UnitPath $within = null, bool $withArchived = true): array
    {
        return $this->sortedUnits(static fn (Unit $unit): bool => $unit->organizationId->equals($organizationId)
            && (!$within instanceof UnitPath || $unit->path->isWithin($within))
            && ($withArchived || $unit->isActive()));
    }

    #[Override]
    public function children(OrganizationId $organizationId, ?UnitId $parentId, bool $withArchived = true): array
    {
        return $this->sortedUnits(static fn (Unit $unit): bool => $unit->organizationId->equals($organizationId)
            && $unit->parentId()?->value === $parentId?->value
            && ($withArchived || $unit->isActive()));
    }

    #[Override]
    public function unitsById(array $ids): array
    {
        $wanted = array_map(static fn (UnitId $id): string => $id->value, $ids);

        return $this->sortedUnits(static fn (Unit $unit): bool => \in_array($unit->id->value, $wanted, true));
    }

    #[Override]
    public function countUnits(OrganizationId $organizationId): int
    {
        return \count($this->units($organizationId));
    }

    #[Override]
    public function deepest(OrganizationId $organizationId, UnitPath $within): int
    {
        return array_reduce($this->units($organizationId, $within), static fn (int $deepest, Unit $unit): int => max($deepest, $unit->path->depth()), 0);
    }

    #[Override]
    public function archiveUnits(OrganizationId $organizationId, UnitPath $within, DateTimeImmutable $now): array
    {
        $archived = [];

        foreach ($this->units($organizationId, $within, false) as $unit) {
            $unit->archive($now);
            $this->units[$unit->id->value] = $unit;
            $archived[] = $unit->id;
        }

        return $archived;
    }

    #[Override]
    public function restoreUnits(OrganizationId $organizationId, UnitPath $within, DateTimeImmutable $archivedAt): array
    {
        $restored = [];

        foreach ($this->units($organizationId, $within) as $unit) {
            if ($unit->archivedAt?->getTimestamp() === $archivedAt->getTimestamp()) {
                $unit->restore();
                $this->units[$unit->id->value] = $unit;
                $restored[] = $unit->id;
            }
        }

        return $restored;
    }

    #[Override]
    public function unitMembership(UnitId $unitId, MemberId $memberId): ?UnitMembership
    {
        return $this->copy($this->unitMemberships[$unitId->value][$memberId->value] ?? null);
    }

    #[Override]
    public function unitMemberships(UnitId $unitId, int $limit, ?string $afterMember = null): array
    {
        $found = array_filter($this->unitMemberships[$unitId->value] ?? [], static fn (UnitMembership $membership): bool => null === $afterMember || strcmp($membership->memberId->value, $afterMember) > 0);
        usort($found, static fn (UnitMembership $a, UnitMembership $b): int => strcmp($a->memberId->value, $b->memberId->value));

        return array_map(static fn (UnitMembership $membership): UnitMembership => clone $membership, \array_slice($found, 0, $limit));
    }

    #[Override]
    public function unitMembershipsOf(MemberId $memberId, ?OrganizationId $organizationId = null): array
    {
        $found = [];

        foreach ($this->unitMemberships as $members) {
            $membership = $members[$memberId->value] ?? null;

            if ($membership instanceof UnitMembership && (!$organizationId instanceof OrganizationId || $membership->organizationId->equals($organizationId))) {
                $found[] = clone $membership;
            }
        }

        usort($found, static fn (UnitMembership $a, UnitMembership $b): int => strcmp($a->unitId->value, $b->unitId->value));

        return $found;
    }

    #[Override]
    public function unitMembershipsAmong(array $unitIds, MemberId $memberId): array
    {
        $wanted = array_map(static fn (UnitId $id): string => $id->value, $unitIds);

        return array_values(array_filter($this->unitMembershipsOf($memberId), static fn (UnitMembership $membership): bool => \in_array($membership->unitId->value, $wanted, true)));
    }

    #[Override]
    public function membersWithin(OrganizationId $organizationId, UnitPath $within, int $limit, ?string $afterMember = null): array
    {
        $members = [];

        foreach ($this->units($organizationId, $within) as $unit) {
            foreach ($this->unitMemberships[$unit->id->value] ?? [] as $membership) {
                if (null === $afterMember || strcmp($membership->memberId->value, $afterMember) > 0) {
                    $members[$membership->memberId->value] = $membership->memberId;
                }
            }
        }

        ksort($members, \SORT_STRING);

        return \array_slice(array_values($members), 0, $limit);
    }

    #[Override]
    public function saveUnitMembership(UnitMembership $membership): void
    {
        $this->unitMemberships[$membership->unitId->value][$membership->memberId->value] = clone $membership;
    }

    #[Override]
    public function deleteUnitMembership(UnitId $unitId, MemberId $memberId): bool
    {
        $found = isset($this->unitMemberships[$unitId->value][$memberId->value]);
        unset($this->unitMemberships[$unitId->value][$memberId->value]);

        return $found;
    }

    #[Override]
    public function reset(): void
    {
        [$this->organizations, $this->memberships, $this->teams, $this->teamMemberships, $this->invitations, $this->hashes, $this->units, $this->unitMemberships] = [[], [], [], [], [], [], [], []];
    }

    /**
     * @param array<string, Invitation> $invitations
     *
     * @return list<Invitation>
     */
    private function newestFirst(array $invitations): array
    {
        usort($invitations, static fn (Invitation $a, Invitation $b): int => [$b->createdAt->getTimestamp(), $b->id->value] <=> [$a->createdAt->getTimestamp(), $a->id->value]);

        return array_map(static fn (Invitation $invitation): Invitation => clone $invitation, $invitations);
    }

    /**
     * @param callable(Unit): bool $wanted
     *
     * @return list<Unit> copies, sorted by path
     */
    private function sortedUnits(callable $wanted): array
    {
        $found = array_values(array_filter($this->units, $wanted));
        usort($found, static fn (Unit $a, Unit $b): int => strcmp($a->path->toString(), $b->path->toString()));

        return array_map(static fn (Unit $unit): Unit => clone $unit, $found);
    }

    /**
     * @template T of object
     *
     * @param T|null $object
     *
     * @return T|null
     */
    private function copy(?object $object): ?object
    {
        return null === $object ? null : clone $object;
    }

    /**
     * @return array{array<string, Organization>, array<string, array<string, Membership>>, array<string, Team>, array<string, array<string, TeamMembership>>, array<string, Invitation>, array<string, string>, array<string, Unit>, array<string, array<string, UnitMembership>>}
     */
    private function snapshot(): array
    {
        $copy = static fn (array $objects): array => array_map(static fn (object $object): object => clone $object, $objects);

        return [
            $copy($this->organizations),
            array_map($copy, $this->memberships),
            $copy($this->teams),
            array_map($copy, $this->teamMemberships),
            $copy($this->invitations),
            $this->hashes,
            $copy($this->units),
            array_map($copy, $this->unitMemberships),
        ];
    }
}
