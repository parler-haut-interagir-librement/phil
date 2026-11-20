# Role inheritance

The roles of a unit follow the model of the organization's roles: a **catalogue** maps each role to permissions, the
configuration declares the roles and permissions, and the roles each member holds are data. What a unit adds is the
tree: **a role granted on a unit holds in that unit and in every unit below it.**

## The unit catalogue

`manager` and `member` are built in. `manager` grants `unit.manage`, the permission to run the units below; `member`
grants nothing by itself and says the member belongs to the unit. The configuration declares other roles, or adds
permissions to the built-in ones:

```yaml
phil_organizations:
    unit_roles:
        instructor: { permissions: [courses.assign, reports.read] }
        manager: { permissions: [reports.read] }
```

A module declares the permissions it checks — `courses.assign` for a course module, `reports.read` for a report — and
the application gives them to unit roles.

## What a member holds in a unit

`Application\UnitAccess` reads it:

```php
$unitAccess->permissions($unit, $member);              // sorted
$unitAccess->can($unit, $member, 'courses.assign');
$unitAccess->hasRole($unit, $member, 'manager');
```

1. A unit that is archived grants nothing, nor a unit of an archived organization, nor anything to someone who is not
   a member of the organization.
2. A member whose **organization** roles grant `units.manage` — owners and admins by default — holds every permission
   of the unit catalogue in every unit.
3. Otherwise a member holds the permissions of every unit role they hold **in the unit or in any unit above it**,
   together.

`hasRole()` answers whether the member holds the role in the unit or above it, whether the unit is archived or not; the
organization's `units.manage` holds no unit role.

```text
Acme
└── Sales          cam: manager
    ├── East       dan: instructor
    │   └── North  dan: member
    └── West
```

Here cam holds `unit.manage` in Sales, East, North and West; dan holds `courses.assign` in East and North, and nothing in
Sales or West. An admin of Acme holds every unit permission everywhere.

The permissions above a unit are read in one query: the unit's path names its ancestors, so the memberships of the
member in those units, sixteen at most, are one indexed read.

## The voter

`OrganizationVoter`, the voter of the organizations, votes on units too, given as the unit, its identifier or the text of
its identifier:

```php
$this->denyAccessUnlessGranted('unit:courses.assign', $unit);
```

```twig
{% if is_granted('unit:unit.manage', unit) %}…{% endif %}
{% if is_granted('unit_role:manager', unit) %}…{% endif %}
```

`unit:<permission>` follows `UnitAccess::can()`, `unit_role:<role>` follows `UnitAccess::hasRole()`. Anyone not signed
in, not a member, or asking of an unknown unit is denied. The organization attributes — `organization:<permission>`,
`organization_role:<role>` — keep their meaning and do not read unit roles.

## Granting

A manager grants, changes and takes away only unit roles whose permissions they hold in that unit: a manager without
`courses.assign` does not name an instructor. A member whose organization roles grant `units.manage` grants every unit
role. [Units](units.md#who-may-change-what) lists who may change what.

## What inheritance does not do

- A unit role grants nothing in the organization: a manager of Sales does not invite members to Acme. Organization
  permissions come from organization roles only.
- Roles flow down, never up or sideways: a manager of East holds nothing in Sales or West.
- The unit memberships are not published as relations of `phil/access-bundle`; the voter and `UnitAccess` read them.
