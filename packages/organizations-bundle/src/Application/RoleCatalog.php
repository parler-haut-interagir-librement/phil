<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Application\Exception\UnknownRole;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;

/**
 * The roles a membership may hold and the permissions of each, from the configuration: the roles of an organization —
 * `owner`, `admin` and `member` built in, others declared —, the roles of a team — `maintainer` and `member` built
 * in, others declared —, or the roles of a unit — `manager` and `member` built in, others declared.
 *
 * A declared permission list adds to a built-in role's; `owner` holds every permission of the catalogue, whatever is
 * declared for it.
 */
final readonly class RoleCatalog
{
    public const string OWNER = 'owner';

    public const string ADMIN = 'admin';

    public const string MEMBER = 'member';

    public const string MAINTAINER = 'maintainer';

    public const string MANAGER = 'manager';

    /**
     * @param array<string, list<string>> $permissions the permissions of each role, by role name
     */
    private function __construct(
        private array $permissions,
    ) {
    }

    /**
     * The roles of an organization.
     *
     * @param array<array-key, mixed> $declared the declared roles, each a list of permissions, by role name
     *
     * @throws InvalidOrganization when a role or a permission is out of shape
     */
    public static function organization(array $declared = []): self
    {
        $admin = array_values(array_diff(Permission::BUILT_IN, [Permission::OWNERS, Permission::ARCHIVE]));

        return self::build([self::OWNER => [], self::ADMIN => $admin, self::MEMBER => [Permission::VIEW]], $declared, self::OWNER);
    }

    /**
     * The roles of a team.
     *
     * @param array<array-key, mixed> $declared the declared roles, each a list of permissions, by role name
     *
     * @throws InvalidOrganization when a role or a permission is out of shape
     */
    public static function team(array $declared = []): self
    {
        return self::build([self::MAINTAINER => [Permission::TEAM_MANAGE], self::MEMBER => []], $declared, null);
    }

    /**
     * The roles of a unit, which hold in the unit and in every unit below it.
     *
     * @param array<array-key, mixed> $declared the declared roles, each a list of permissions, by role name
     *
     * @throws InvalidOrganization when a role or a permission is out of shape
     */
    public static function unit(array $declared = []): self
    {
        return self::build([self::MANAGER => [Permission::UNIT_MANAGE], self::MEMBER => []], $declared, null);
    }

    /**
     * @return list<string> the role names, the built-in ones first
     */
    public function names(): array
    {
        return array_keys($this->permissions);
    }

    public function has(string $role): bool
    {
        return isset($this->permissions[$role]);
    }

    /**
     * @return list<string> every permission a role of the catalogue holds, sorted
     */
    public function allPermissions(): array
    {
        $all = array_values(array_unique(array_merge(...array_values($this->permissions))));
        sort($all, \SORT_STRING);

        return $all;
    }

    /**
     * Checks that every role is declared.
     *
     * @throws UnknownRole when one is not
     */
    public function check(Roles $roles): Roles
    {
        foreach ($roles->names as $role) {
            if (!$this->has($role)) {
                throw UnknownRole::named($role);
            }
        }

        return $roles;
    }

    /**
     * @return list<string> the permissions of the roles, sorted; a role not declared grants nothing
     */
    public function permissionsOf(Roles $roles): array
    {
        $granted = [];

        foreach ($roles->names as $role) {
            foreach ($this->permissions[$role] ?? [] as $permission) {
                $granted[$permission] = $permission;
            }
        }

        ksort($granted, \SORT_STRING);

        return array_values($granted);
    }

    public function grants(Roles $roles, string $permission): bool
    {
        return \in_array($permission, $this->permissionsOf($roles), true);
    }

    /**
     * Whether a member holding `$holder` holds every permission of `$roles`: the bound on what they may grant, change
     * or take away.
     */
    public function covers(Roles $holder, Roles $roles): bool
    {
        return [] === array_diff($this->permissionsOf($roles), $this->permissionsOf($holder));
    }

    /**
     * @param array<string, list<string>> $builtIn
     * @param array<array-key, mixed>     $declared
     */
    private static function build(array $builtIn, array $declared, ?string $everything): self
    {
        $permissions = $builtIn;

        foreach ($declared as $role => $list) {
            if (!\is_string($role) || 1 !== preg_match(Roles::NAME_PATTERN, $role)) {
                throw InvalidOrganization::because(\sprintf('A role is 1 to 64 lowercase letters, digits or "_", starting with a letter; "%s" is not.', $role));
            }

            if (!\is_array($list)) {
                throw InvalidOrganization::because(\sprintf('The permissions of the role "%s" are a list.', $role));
            }

            foreach ($list as $permission) {
                if (!\is_string($permission) || 1 !== preg_match(Permission::NAME_PATTERN, $permission)) {
                    throw InvalidOrganization::because(\sprintf('A permission is 1 to 128 lowercase letters, digits, "_", "." or "-", starting with a letter; the role "%s" lists one that is not.', $role));
                }

                $permissions[$role][] = $permission;
            }

            $permissions[$role] ??= [];
        }

        if (null !== $everything) {
            $all = array_merge(Permission::BUILT_IN, ...array_values($permissions));
            $permissions[$everything] = $all;
        }

        foreach ($permissions as $role => $list) {
            $unique = array_values(array_unique($list));
            sort($unique, \SORT_STRING);
            $permissions[$role] = $unique;
        }

        return new self($permissions);
    }
}
