# Units

An organization holds **units** — departments, services, branches, classes — in a tree: a unit is a root, or the
child of another unit of the same organization. A unit has a name and a place in the tree; it is active or archived;
members of the organization belong to it with **unit roles**, which hold in the unit and in every unit below it, as
[Role inheritance](role-inheritance.md) explains.

## The tree

`Application\Units` changes the tree; each change runs in one transaction, locks its organization, and dispatches its
event once committed:

```php
$sales = $units->create($acme->id, $actor, 'Sales');                    // a root
$east = $units->create($acme->id, $actor, 'East', $sales->id);           // under Sales
$units->rename($east->id, $actor, 'East coast');
$units->move($east->id, $actor, $support->id);                          // with every unit below it
$units->move($east->id, $actor, null);                                  // to the root
$units->archive($sales->id, $actor);                                    // with every unit below it
$units->restore($sales->id, $actor);
```

- **Create.** The parent is a unit of the same organization, active; without one the unit is a root. Its identifier
  is a UUID version 7.
- **Rename.** A unit's name is 1 to 200 characters, as an organization's; renaming it to the same name changes
  nothing.
- **Move.** The unit moves with every unit below it. A move under the unit itself or under one of the units below it
  throws `UnitCycle`; a move to the parent it has changes nothing. Two moves of one organization never run at once, so
  that they cannot make a cycle together.
- **Archive.** The unit and every unit below it that is still active are archived at the same moment; their members
  stay. An archived unit is read only: no unit is created under it or moved into it, it is not renamed or moved, and its
  members do not change, except a member who leaves it. `UnitArchived` says so.
- **Restore.** The unit and the units below it archived at the same moment as it come back; a unit archived before
  stays archived. A unit whose parent is archived is not restored until its parent is.

### Limits

A unit is at most **16 levels** deep, a root being at 1, and an organization holds at most **10 000 units**, archived
ones included: a change past either throws `InvalidOrganization`. A move checks the deepest unit below the one that
moves. These bounds keep each question of the tree to one bounded query.

## Members

```php
$units->addMember($east->id, $actor, $member, ['instructor']);
$units->changeRoles($east->id, $actor, $member, ['manager']);
$units->removeMember($east->id, $actor, $member);                      // $actor === $member: the member leaves
```

A member of a unit is a member of the organization: adding anyone else throws `NotAMember`, and adding a member twice
throws `AlreadyMember`. A member who leaves the organization, is removed from it or is erased leaves its units, with a
`UnitMemberRemoved` for each.

With `phil_organizations.units.single_membership: true`, a member belongs to **one unit of an organization at most**:
adding them to a second one throws `AlreadyMember::ofAnotherUnit()`. They are removed from the first unit before they
join another. The rule holds per organization: a member of two organizations belongs to one unit in each.

## Who may change what

| Change | Allowed to |
|---|---|
| Create a root unit | Members whose organization roles grant `units.manage` — owners and admins by default |
| Create a unit under a unit, rename a unit | Those who manage that unit |
| Move, archive or restore a unit | Those who manage its parent — `units.manage` for a root — and, for a move, the new parent too |
| Add, change or remove a member | Those who manage the unit, within the unit roles they hold there |

A member **manages** a unit when their organization roles grant `units.manage`, or when the unit roles they hold in the
unit or above it grant `unit.manage` — the built-in `manager`. A manager therefore runs the units below their own unit,
not the unit itself: who placed them there moves and archives it. They grant, change and take away only unit roles whose
permissions they hold in that unit; `units.manage` grants every unit role. A refusal throws `PermissionDenied`; a change
in an archived organization throws `OrganizationArchived`.

## Questions of other modules

`Application\UnitTree` answers what other modules ask of the tree — a catalogue for a unit, a report per unit, a
dashboard — read only and without permission checks. It is the stable API a module builds on:

| Method | Answers |
|---|---|
| `unit()`, `get()` | A unit, or `null` / `UnknownUnit` |
| `units($organizationId, $withArchived)` | Every unit of the organization, sorted by path |
| `roots()`, `children()` | The roots, or the children of a unit |
| `ancestors($unitId)` | The units above, from the root |
| `descendants($unitId, $withSelf, $withArchived)` | The units below |
| `isWithin($unitId, $ancestorId)` | Whether a unit is another or below it |
| `members($unitId, $limit, $afterMember)` | The members of the unit itself, sorted, a page at a time |
| `membersWithin($unitId, $limit, $afterMember)` | The members of the unit and of the units below it, each once |
| `membershipsOf($organizationId, $memberId)` | A member's unit memberships |
| `unitIdsOf($organizationId, $memberId, $withAncestors)` | The units a member belongs to and, with ancestors, every unit above them |

Archived units are left out unless asked for. Every list of units is sorted by path: a parent comes before its children,
and the order of siblings follows their identifiers, byte by byte. A screen that lists siblings by name sorts them
itself.

With `phil/reporting-bundle`, the training reports read the tree through `ReportingUnitDirectory`: see
[Integrations](integrations.md#philreporting-bundle).

A catalogue that belongs to a unit and is offered to the members of that unit and of the units below it reads
`unitIdsOf($organizationId, $member, withAncestors: true)` and keeps the catalogues of those units.

## How the tree is stored

Each unit row keeps its parent and its **materialized path**, the identifiers of its ancestors and its own written
`/root/child/unit/`, with its depth. The path is indexed with the organization, and compares byte for byte — `ascii_bin`
on MySQL and MariaDB, the `C` collation on PostgreSQL, the binary order of SQLite. The units below a unit are the paths
that sort from its path to the same path with its last `/` turned into `0`, one range of the index, without a `LIKE`
pattern to escape. A unit's ancestors are named by its path, so the unit roles a member holds above a unit are one
indexed read of `phil_organization_unit_member`. A move rewrites every path below in one `UPDATE`.

A closure table was measured against it: a second table holding a row per ancestor of each unit, joined for every
question. On a tree of 5 850 units — 10 roots of 8 children each, four levels deep — with 23 400 unit memberships, the
average of each operation over a few hundred runs was:

| Operation | Path, MySQL 8.4 | Closure, MySQL 8.4 | Path, PostgreSQL 17 | Closure, PostgreSQL 17 |
|---|---|---|---|---|
| A member's roles above a unit four levels deep | 351 µs (two queries) | 199 µs | 443 µs (two queries) | 483 µs |
| The 73 units below a unit of the second level | 228 µs | 286 µs | 284 µs | 796 µs |
| The members within a unit of the second level | 478 µs | 466 µs | 2 491 µs | 2 541 µs |
| A move of 73 units | 1 435 µs | 2 902 µs | 1 469 µs | 2 959 µs |
| A new unit | 270 µs | 942 µs | 282 µs | 1 116 µs |

The path costs one read of the unit before the roles above it, which a permission check makes anyway to know whether the
unit is archived; the closure table pays a fourfold table — 23 575 rows for 5 850 units — and two to four times the
cost of every write. The bundle keeps the path. `tests/Benchmark/UnitTreeBench.php` measures the same operations through
the bundle's services, on SQLite.

The tables are `phil_organization_unit` and `phil_organization_unit_member`; [Installation](installation.md#the-tables)
says how they are created.

## Events

| Event | Dispatched when | Carries |
|---|---|---|
| `UnitCreated` | A unit is created | organization, unit, parent or `null`, actor |
| `UnitRenamed` | A unit is renamed | organization, unit, actor |
| `UnitMoved` | A unit moves | organization, unit, previous parent, parent, actor |
| `UnitArchived`, `UnitRestored` | A unit is archived or restored | organization, unit, every unit archived or restored with it, actor |
| `UnitMemberAdded` | A member joins a unit | organization, unit, member, unit roles, actor |
| `UnitMemberRolesChanged` | A member's unit roles change | organization, unit, member, previous roles, roles, actor |
| `UnitMemberRemoved` | A member leaves a unit, or the organization | organization, unit, member, unit roles held, actor when one removed them |

The units below a unit that moves are not announced one by one: `UnitMoved` names the unit, and their paths follow.

## Administration pages

The bundle draws the pages of the units with the recipes of the PHIL UX kit — `Badge`, `Button`, `Input`,
`NativeSelect`, `Table` and `Tree` —, which the Symfony UX Toolkit installs in the application:

```sh
php bin/console ux:install badge button input native-select table tree --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The recipe of the bundle imports their routes under `/admin/organizations`:

| Route | Page |
|---|---|
| `phil_organizations_units` | The tree of an organization, the form of a new unit |
| `phil_organizations_units_create` | The new unit, posted |
| `phil_organizations_unit` | A unit: the units above, its state, the units below, its members, and its forms |
| `phil_organizations_unit_rename`, `_move`, `_archive`, `_restore` | The forms of a unit |
| `phil_organizations_unit_member_add`, `_member_remove` | The forms of its members |

They need Twig, the router, the CSRF protection of FrameworkBundle and the token storage of Symfony Security. Every
member of the organization signed in sees the pages; each form shows to those who may use it, and each change goes
through `Units`, which checks the permissions again. A refusal for the content of a change shows the page again with its
reason and a `422`; a change the member may not make is a `403`; an unknown unit or organization a `404`. The forms
carry Symfony's stateless CSRF token `phil_organizations_units`, which the bundle declares, and a form sent from another
site is refused with a `400`.

The pages extend `phil_organizations.units.layout`, which fills the blocks `title` and `main`. Their labels are in the
`phil_organizations` translation domain, in English and French. The recipes are named after the components
subdirectory of the `ux_toolkit` configuration, or `phil_organizations.units.kit_subdirectory` where the Toolkit is not
enabled, as in production.
