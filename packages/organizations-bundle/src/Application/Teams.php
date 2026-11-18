<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Exception\AlreadyMember;
use Phil\OrganizationsBundle\Application\Exception\NotAMember;
use Phil\OrganizationsBundle\Application\Exception\OrganizationArchived;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\Exception\SlugTaken;
use Phil\OrganizationsBundle\Application\Exception\UnknownTeam;
use Phil\OrganizationsBundle\Domain\Event\TeamCreated;
use Phil\OrganizationsBundle\Domain\Event\TeamDeleted;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberAdded;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRemoved;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Team;
use Phil\OrganizationsBundle\Domain\Model\TeamMembership;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\Slug\Slug;
use Phil\Slug\Slugger;
use Psr\Clock\ClockInterface;

/**
 * Teams inside an organization, each with its own roles, from the catalogue of team roles.
 *
 * A member who holds `teams.manage` in the organization creates, renames and deletes teams and manages the members of
 * any team. A member whose team roles grant `team.manage` — the `maintainer` — renames that team and manages its
 * members, granting only team roles whose permissions they hold in the team. Every member of a team is a member of
 * the organization; leaving or being removed from the organization removes them from its teams.
 */
final readonly class Teams
{
    public function __construct(
        private OrganizationStore $store,
        private UnitOfWork $work,
        private OrganizationAccess $access,
        private RoleCatalog $teamRoles,
        private Slugger $slugger,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws UnknownTeam when there is none
     */
    public function get(TeamId $id): Team
    {
        return $this->store->team($id) ?? throw UnknownTeam::named($id->value);
    }

    /**
     * @return list<Team> sorted by slug
     */
    public function teams(OrganizationId $organizationId): array
    {
        return $this->store->teams($organizationId);
    }

    /**
     * @return list<TeamMembership> sorted by member
     */
    public function members(TeamId $teamId): array
    {
        return $this->store->teamMemberships($teamId);
    }

    public function membership(TeamId $teamId, MemberId $memberId): ?TeamMembership
    {
        return $this->store->teamMembership($teamId, $memberId);
    }

    /**
     * @return list<string> the permissions the member holds in the team, sorted
     */
    public function permissions(TeamId $teamId, MemberId $memberId): array
    {
        $membership = $this->store->teamMembership($teamId, $memberId);

        return $membership instanceof TeamMembership ? $this->teamRoles->permissionsOf($membership->roles) : [];
    }

    /**
     * @throws SlugTaken when another team of the organization has the slug
     */
    public function create(OrganizationId $organizationId, MemberId $actor, string $name, ?string $slug = null): Team
    {
        $teamName = OrganizationName::fromString($name);
        $teamSlug = null === $slug ? SlugText::made($this->slugger, $teamName->value) : SlugText::given($slug);

        return $this->work->run(function (EventRecorder $events) use ($organizationId, $actor, $teamName, $teamSlug): Team {
            $organization = $this->access->lock($organizationId);
            $this->access->require($organization, $actor, Permission::TEAMS);
            $this->freeSlug($organizationId, $teamSlug, null);

            $team = Team::create(TeamId::generate(), $organizationId, $teamName, $teamSlug, $this->clock->now());
            $this->store->saveTeam($team);
            $events->record(new TeamCreated($organizationId->value, $team->id->value, $actor->value));

            return $team;
        });
    }

    public function rename(TeamId $teamId, MemberId $actor, string $name, ?string $slug = null): Team
    {
        $teamName = OrganizationName::fromString($name);
        $teamSlug = null === $slug ? null : SlugText::given($slug);

        return $this->work->run(function () use ($teamId, $actor, $teamName, $teamSlug): Team {
            [, $team] = $this->manage($teamId, $actor);

            if ($teamSlug instanceof Slug) {
                $this->freeSlug($team->organizationId, $teamSlug, $team);
            }

            $team->rename($teamName, $teamSlug);
            $this->store->saveTeam($team);

            return $team;
        });
    }

    public function delete(TeamId $teamId, MemberId $actor): void
    {
        $this->work->run(function (EventRecorder $events) use ($teamId, $actor): void {
            $team = $this->get($teamId);
            $organization = $this->access->lock($team->organizationId);
            $this->access->require($organization, $actor, Permission::TEAMS);

            foreach ($this->store->teamMemberships($teamId) as $membership) {
                $events->record(new TeamMemberRemoved($team->organizationId->value, $teamId->value, $membership->memberId->value, $membership->roles->names, $actor->value));
            }

            $this->store->deleteTeam($teamId);
            $events->record(new TeamDeleted($team->organizationId->value, $teamId->value, $actor->value));
        });
    }

    /**
     * @param iterable<string> $roles roles of the team
     *
     * @throws NotAMember    when the member is not a member of the organization
     * @throws AlreadyMember when the member is already in the team
     */
    public function addMember(TeamId $teamId, MemberId $actor, MemberId $member, iterable $roles): TeamMembership
    {
        $granted = $this->teamRoles->check(Roles::of($roles));

        return $this->work->run(function (EventRecorder $events) use ($teamId, $actor, $member, $granted): TeamMembership {
            [$actorRoles, $team] = $this->manage($teamId, $actor);
            $this->bound($actorRoles, $granted);

            if (!$this->store->membership($team->organizationId, $member) instanceof Membership) {
                throw NotAMember::ofOrganization($team->organizationId->value);
            }

            if ($this->store->teamMembership($teamId, $member) instanceof TeamMembership) {
                throw AlreadyMember::ofTeam($teamId->value);
            }

            $membership = TeamMembership::join($teamId, $member, $granted, $this->clock->now());
            $this->store->saveTeamMembership($membership);
            $events->record(new TeamMemberAdded($team->organizationId->value, $teamId->value, $member->value, $granted->names, $actor->value));

            return $membership;
        });
    }

    /**
     * @param iterable<string> $roles roles of the team
     */
    public function changeRoles(TeamId $teamId, MemberId $actor, MemberId $member, iterable $roles): TeamMembership
    {
        $roles = $this->teamRoles->check(Roles::of($roles));

        return $this->work->run(function (EventRecorder $events) use ($teamId, $actor, $member, $roles): TeamMembership {
            [$actorRoles, $team] = $this->manage($teamId, $actor);
            $membership = $this->store->teamMembership($teamId, $member) ?? throw NotAMember::ofTeam($teamId->value);
            $this->bound($actorRoles, $membership->roles);
            $this->bound($actorRoles, $roles);
            $previous = $membership->roles;

            if (!$previous->equals($roles)) {
                $membership->changeRoles($roles);
                $this->store->saveTeamMembership($membership);
                $events->record(new TeamMemberRolesChanged($team->organizationId->value, $teamId->value, $member->value, $previous->names, $roles->names, $actor->value));
            }

            return $membership;
        });
    }

    /**
     * Removes a member from the team; a member removing themselves leaves it.
     */
    public function removeMember(TeamId $teamId, MemberId $actor, MemberId $member): void
    {
        $this->work->run(function (EventRecorder $events) use ($teamId, $actor, $member): void {
            $leaves = $actor->equals($member);
            [$actorRoles, $team] = $leaves ? $this->own($teamId) : $this->manage($teamId, $actor);
            $membership = $this->store->teamMembership($teamId, $member) ?? throw NotAMember::ofTeam($teamId->value);
            $this->bound($actorRoles, $membership->roles);

            $this->store->deleteTeamMembership($teamId, $member);
            $events->record(new TeamMemberRemoved($team->organizationId->value, $teamId->value, $member->value, $membership->roles->names, $leaves ? null : $actor->value));
        });
    }

    /**
     * The team, and the roles the actor manages it with: every team role when they hold `teams.manage` in the
     * organization, their own team roles when these grant `team.manage`.
     *
     * @return array{Roles|null, Team} null roles when the actor holds `teams.manage`, and may grant every team role
     *
     * @throws PermissionDenied     when the actor may not manage the team
     * @throws OrganizationArchived when the organization is archived
     */
    private function manage(TeamId $teamId, MemberId $actor): array
    {
        $team = $this->get($teamId);
        $organization = $this->access->lock($team->organizationId);

        if (\in_array(Permission::TEAMS, $this->access->permissions($organization->id, $actor), true)) {
            return [null, $team];
        }

        $this->access->require($organization, $actor, Permission::VIEW);
        $this->active($organization);
        $roles = $this->store->teamMembership($teamId, $actor)?->roles;

        if (!$roles instanceof Roles || !$this->teamRoles->grants($roles, Permission::TEAM_MANAGE)) {
            throw PermissionDenied::lacking(Permission::TEAM_MANAGE);
        }

        return [$roles, $team];
    }

    /**
     * The team, locked through its organization, for a member who leaves it.
     *
     * @return array{null, Team}
     */
    private function own(TeamId $teamId): array
    {
        $team = $this->get($teamId);
        $this->access->lock($team->organizationId);

        return [null, $team];
    }

    private function active(Organization $organization): void
    {
        if (!$organization->isActive()) {
            throw OrganizationArchived::of($organization->id->value);
        }
    }

    /**
     * @throws PermissionDenied when a team maintainer would grant or touch roles wider than their own
     */
    private function bound(?Roles $actor, Roles $roles): void
    {
        if ($actor instanceof Roles && !$this->teamRoles->covers($actor, $roles)) {
            throw PermissionDenied::widerRoles();
        }
    }

    private function freeSlug(OrganizationId $organizationId, Slug $slug, ?Team $self): void
    {
        $other = $this->store->teamBySlug($organizationId, $slug);

        if ($other instanceof Team && (!$self instanceof Team || !$other->id->equals($self->id))) {
            throw SlugTaken::of($slug->value);
        }
    }
}
