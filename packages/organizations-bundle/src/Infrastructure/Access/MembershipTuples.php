<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Access;

use Phil\OrganizationsBundle\Domain\Event\MemberJoined;
use Phil\OrganizationsBundle\Domain\Event\MemberLeft;
use Phil\OrganizationsBundle\Domain\Event\MemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Event\TeamCreated;
use Phil\OrganizationsBundle\Domain\Event\TeamDeleted;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberAdded;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRemoved;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRolesChanged;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Subject;

/**
 * Publishes memberships and roles as tuples of `phil/resource-permissions`, once each change is committed: a role held
 * is a tuple `organization:<id>#<role>@member:<id>`, a team role `team:<id>#<role>@member:<id>`, a team's organization
 * `team:<id>#organization@organization:<id>`.
 *
 * The tuples are written through the store of `phil/access-bundle`, in the partition of its scope: with the tuples
 * partitioned by tenant, they go to the active tenant, and a change made outside any tenant fails rather than write
 * them nowhere in particular.
 */
final readonly class MembershipTuples
{
    public function __construct(
        private RelationWriter $writer,
        private OrganizationRelations $relations,
    ) {
    }

    public function onMemberJoined(MemberJoined $event): void
    {
        $this->writer->update([], $this->tuples($this->organization($event->organizationId), $event->memberId, $event->roles));
    }

    public function onMemberRolesChanged(MemberRolesChanged $event): void
    {
        $organization = $this->organization($event->organizationId);
        $this->writer->update(
            $this->tuples($organization, $event->memberId, array_values(array_diff($event->previousRoles, $event->roles))),
            $this->tuples($organization, $event->memberId, array_values(array_diff($event->roles, $event->previousRoles))),
        );
    }

    public function onMemberLeft(MemberLeft $event): void
    {
        $this->writer->update($this->tuples($this->organization($event->organizationId), $event->memberId, $event->roles), []);
    }

    public function onTeamCreated(TeamCreated $event): void
    {
        $this->writer->grant($this->team($event->teamId), OrganizationRelations::ORGANIZATION_RELATION, $this->organization($event->organizationId));
    }

    public function onTeamDeleted(TeamDeleted $event): void
    {
        $this->writer->forget($this->team($event->teamId));
    }

    public function onTeamMemberAdded(TeamMemberAdded $event): void
    {
        $this->writer->update([], $this->tuples($this->team($event->teamId), $event->memberId, $event->roles));
    }

    public function onTeamMemberRolesChanged(TeamMemberRolesChanged $event): void
    {
        $team = $this->team($event->teamId);
        $this->writer->update(
            $this->tuples($team, $event->memberId, array_values(array_diff($event->previousRoles, $event->roles))),
            $this->tuples($team, $event->memberId, array_values(array_diff($event->roles, $event->previousRoles))),
        );
    }

    public function onTeamMemberRemoved(TeamMemberRemoved $event): void
    {
        $this->writer->update($this->tuples($this->team($event->teamId), $event->memberId, $event->roles), []);
    }

    /**
     * @param list<string> $roles
     *
     * @return list<RelationTuple>
     */
    private function tuples(ResourceReference $object, string $memberId, array $roles): array
    {
        $member = Subject::of(ResourceReference::of(OrganizationRelations::MEMBER_KIND, $memberId));

        return array_map(static fn (string $role): RelationTuple => new RelationTuple($object, $role, $member), $roles);
    }

    private function organization(string $id): ResourceReference
    {
        return ResourceReference::of($this->relations->organizationKind, $id);
    }

    private function team(string $id): ResourceReference
    {
        return ResourceReference::of($this->relations->teamKind, $id);
    }
}
