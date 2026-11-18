<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Exception\AlreadyMember;
use Phil\OrganizationsBundle\Application\Exception\LastOwner;
use Phil\OrganizationsBundle\Application\Exception\NotAMember;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\Exception\UnknownRole;
use Phil\OrganizationsBundle\Domain\Event\MemberJoined;
use Phil\OrganizationsBundle\Domain\Event\MemberLeft;
use Phil\OrganizationsBundle\Domain\Event\MemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRemoved;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\Status\LeaveReason;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Psr\Clock\ClockInterface;

/**
 * The members of an organization and their roles.
 *
 * A member who manages members — `members.manage` — adds, changes and removes only roles whose permissions they hold
 * themselves, and only members whose roles are within their own: an admin neither names an owner nor removes one. The
 * last owner can never leave, be removed or lose the owner role: the change throws `LastOwner`, and the organization
 * is never left without someone who may grant and revoke.
 */
final readonly class Members
{
    public function __construct(
        private OrganizationStore $store,
        private UnitOfWork $work,
        private OrganizationAccess $access,
        private ClockInterface $clock,
    ) {
    }

    public function membership(OrganizationId $organizationId, MemberId $memberId): ?Membership
    {
        return $this->store->membership($organizationId, $memberId);
    }

    /**
     * @param int<1, 1000> $limit
     *
     * @return list<Membership> sorted by member
     */
    public function members(OrganizationId $organizationId, int $limit = 100, ?string $afterMember = null): array
    {
        return $this->store->memberships($organizationId, $limit, $afterMember);
    }

    /**
     * @param iterable<string> $roles
     *
     * @throws InvalidOrganization when the roles are out of shape
     * @throws UnknownRole         when a role is not declared
     * @throws PermissionDenied    when the actor may not manage members, or not grant these roles
     * @throws AlreadyMember       when the member is already a member
     */
    public function add(OrganizationId $organizationId, MemberId $actor, MemberId $member, iterable $roles): Membership
    {
        $granted = $this->access->roles()->check(Roles::of($roles));

        return $this->work->run(function (EventRecorder $events) use ($organizationId, $actor, $member, $granted): Membership {
            $organization = $this->access->lock($organizationId);
            $this->bound($this->access->require($organization, $actor, Permission::MEMBERS), $granted);

            if ($this->store->membership($organizationId, $member) instanceof Membership) {
                throw AlreadyMember::ofOrganization($organizationId->value);
            }

            $membership = Membership::join($organizationId, $member, $granted, JoinMethod::Added, $this->clock->now());
            $this->store->saveMembership($membership);
            $events->record(new MemberJoined($organizationId->value, $member->value, $granted->names, JoinMethod::Added, $actor->value));

            return $membership;
        });
    }

    /**
     * @param iterable<string> $roles
     *
     * @throws LastOwner when the change would take the owner role from the last owner
     */
    public function changeRoles(OrganizationId $organizationId, MemberId $actor, MemberId $member, iterable $roles): Membership
    {
        $roles = $this->access->roles()->check(Roles::of($roles));

        return $this->work->run(function (EventRecorder $events) use ($organizationId, $actor, $member, $roles): Membership {
            $organization = $this->access->lock($organizationId);
            $actorMembership = $this->access->require($organization, $actor, Permission::MEMBERS);
            $membership = $this->member($organization, $member);
            $this->bound($actorMembership, $membership->roles);
            $this->bound($actorMembership, $roles);

            $previous = $membership->roles;

            if ($previous->equals($roles)) {
                return $membership;
            }

            $this->keepAnOwner($organization, $previous, $roles);
            $membership->changeRoles($roles);
            $this->store->saveMembership($membership);
            $events->record(new MemberRolesChanged($organizationId->value, $member->value, $previous->names, $roles->names, $actor->value));

            return $membership;
        });
    }

    /**
     * Removes a member, and their memberships of the organization's teams; a member removing themselves leaves.
     *
     * @throws LastOwner when the member is the last owner
     */
    public function remove(OrganizationId $organizationId, MemberId $actor, MemberId $member): void
    {
        if ($actor->equals($member)) {
            $this->leave($organizationId, $member);

            return;
        }

        $this->work->run(function (EventRecorder $events) use ($organizationId, $actor, $member): void {
            $organization = $this->access->lock($organizationId);
            $actorMembership = $this->access->require($organization, $actor, Permission::MEMBERS);
            $membership = $this->member($organization, $member);
            $this->bound($actorMembership, $membership->roles);
            $this->drop($organization, $membership, LeaveReason::Removed, $actor, $events);
        });
    }

    /**
     * The member leaves the organization and its teams, an archived one too.
     *
     * @throws LastOwner when the member is the last owner
     */
    public function leave(OrganizationId $organizationId, MemberId $member): void
    {
        $this->work->run(function (EventRecorder $events) use ($organizationId, $member): void {
            $organization = $this->access->lock($organizationId);
            $this->drop($organization, $this->member($organization, $member), LeaveReason::Left, null, $events);
        });
    }

    private function drop(Organization $organization, Membership $membership, LeaveReason $reason, ?MemberId $actor, EventRecorder $events): void
    {
        $this->keepAnOwner($organization, $membership->roles, null);

        foreach ($this->store->teamMembershipsOf($membership->memberId, $organization->id) as $teamMembership) {
            $events->record(new TeamMemberRemoved($organization->id->value, $teamMembership->teamId->value, $membership->memberId->value, $teamMembership->roles->names));
        }

        $this->store->deleteMembership($organization->id, $membership->memberId);
        $events->record(new MemberLeft($organization->id->value, $membership->memberId->value, $membership->roles->names, $reason, $actor?->value));
    }

    private function member(Organization $organization, MemberId $member): Membership
    {
        return $this->store->membership($organization->id, $member) ?? throw NotAMember::ofOrganization($organization->id->value);
    }

    /**
     * @throws PermissionDenied when the roles grant a permission the actor does not hold
     */
    private function bound(Membership $actor, Roles $roles): void
    {
        if (!$this->access->roles()->covers($actor->roles, $roles)) {
            throw PermissionDenied::widerRoles();
        }
    }

    /**
     * @throws LastOwner when the change takes the owner role from the last owner
     */
    private function keepAnOwner(Organization $organization, Roles $previous, ?Roles $next): void
    {
        $loses = $previous->has(RoleCatalog::OWNER) && !($next?->has(RoleCatalog::OWNER) ?? false);

        if ($loses && $this->store->countWithRole($organization->id, RoleCatalog::OWNER) <= 1) {
            throw LastOwner::of($organization->id->value);
        }
    }
}
