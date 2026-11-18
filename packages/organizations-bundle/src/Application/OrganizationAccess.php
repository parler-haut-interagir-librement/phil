<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Exception\NotAMember;
use Phil\OrganizationsBundle\Application\Exception\OrganizationArchived;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\Exception\UnknownOrganization;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;

/**
 * What a member may do in an organization: the permissions of their roles, by the catalogue of the configuration.
 *
 * An archived organization is read only: its members keep `organization.view`, and those who may archive it keep
 * `organization.archive`, to restore it; every other permission is refused until it is restored.
 */
final readonly class OrganizationAccess
{
    public const array ARCHIVED_PERMISSIONS = [Permission::VIEW, Permission::ARCHIVE];

    public function __construct(
        private OrganizationStore $store,
        private RoleCatalog $roles,
    ) {
    }

    public function roles(): RoleCatalog
    {
        return $this->roles;
    }

    public function can(OrganizationId $organizationId, MemberId $memberId, string $permission): bool
    {
        $organization = $this->store->organization($organizationId);
        $membership = $this->store->membership($organizationId, $memberId);

        return $organization instanceof Organization && $membership instanceof Membership && $this->allows($organization, $membership, $permission);
    }

    public function hasRole(OrganizationId $organizationId, MemberId $memberId, string $role): bool
    {
        return $this->store->membership($organizationId, $memberId)?->roles->has($role) ?? false;
    }

    /**
     * @return list<string> the permissions the member holds in the organization now, sorted; none for a non-member
     */
    public function permissions(OrganizationId $organizationId, MemberId $memberId): array
    {
        $organization = $this->store->organization($organizationId);
        $membership = $this->store->membership($organizationId, $memberId);

        if (!$organization instanceof Organization || !$membership instanceof Membership) {
            return [];
        }

        $permissions = $this->roles->permissionsOf($membership->roles);

        return $organization->isActive() ? $permissions : array_values(array_intersect($permissions, self::ARCHIVED_PERMISSIONS));
    }

    /**
     * @throws UnknownOrganization when there is none
     */
    public function organization(OrganizationId $organizationId): Organization
    {
        return $this->store->organization($organizationId) ?? throw UnknownOrganization::named($organizationId->value);
    }

    /**
     * The organization, read for a change and locked until the transaction ends.
     *
     * @throws UnknownOrganization when there is none
     */
    public function lock(OrganizationId $organizationId): Organization
    {
        return $this->store->organization($organizationId, true) ?? throw UnknownOrganization::named($organizationId->value);
    }

    /**
     * The actor's membership, when they hold the permission in the organization.
     *
     * @throws NotAMember           when the actor is not a member
     * @throws OrganizationArchived when the organization is archived and the permission changes it
     * @throws PermissionDenied     when the actor's roles do not grant the permission
     */
    public function require(Organization $organization, MemberId $actor, string $permission): Membership
    {
        $membership = $this->store->membership($organization->id, $actor) ?? throw NotAMember::ofOrganization($organization->id->value);

        if (!$organization->isActive() && !\in_array($permission, self::ARCHIVED_PERMISSIONS, true)) {
            throw OrganizationArchived::of($organization->id->value);
        }

        if (!$this->roles->grants($membership->roles, $permission)) {
            throw PermissionDenied::lacking($permission);
        }

        return $membership;
    }

    private function allows(Organization $organization, Membership $membership, string $permission): bool
    {
        if (!$organization->isActive() && !\in_array($permission, self::ARCHIVED_PERMISSIONS, true)) {
            return false;
        }

        return $this->roles->grants($membership->roles, $permission);
    }
}
