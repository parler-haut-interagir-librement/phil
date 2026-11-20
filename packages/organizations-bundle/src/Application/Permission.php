<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

/**
 * The permissions the bundle checks itself. The configuration declares others, as many as the application needs, and
 * gives them to roles: permissions are configuration, the roles each member holds are data.
 *
 * The owner holds every permission, declared or built in. The admin holds every built-in permission but
 * `owners.manage` and `organization.archive`; the member holds `organization.view`.
 */
final readonly class Permission
{
    /** See the organization and its members. */
    public const string VIEW = 'organization.view';

    /** Rename the organization, change its slug and its verified domain. */
    public const string MANAGE = 'organization.manage';

    /** Archive and restore the organization. */
    public const string ARCHIVE = 'organization.archive';

    /** Create and revoke invitations. */
    public const string INVITE = 'members.invite';

    /** Add members, change their roles, remove them. */
    public const string MEMBERS = 'members.manage';

    /** Give and take the owner role. */
    public const string OWNERS = 'owners.manage';

    /** Create and delete teams, and manage the members of any team. */
    public const string TEAMS = 'teams.manage';

    /** Create, rename, move, archive and restore any unit, and manage the members of any unit. */
    public const string UNITS = 'units.manage';

    /** Act on behalf of the organization. */
    public const string ACT_ON_BEHALF = 'organization.act_on_behalf';

    /** Manage the members of one team: a permission of the team roles. */
    public const string TEAM_MANAGE = 'team.manage';

    /** Manage one unit and the units below it: a permission of the unit roles. */
    public const string UNIT_MANAGE = 'unit.manage';

    public const array BUILT_IN = [self::VIEW, self::MANAGE, self::ARCHIVE, self::INVITE, self::MEMBERS, self::OWNERS, self::TEAMS, self::UNITS, self::ACT_ON_BEHALF];

    /** The rule of a permission's name: 1 to 128 lowercase letters, digits, "_", "." or "-", starting with a letter. */
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_.-]{0,127}$/D';

    private function __construct()
    {
    }
}
