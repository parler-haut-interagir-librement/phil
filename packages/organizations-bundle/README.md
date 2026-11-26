# phil/organizations-bundle

**Organizations for Symfony, for anyone building their own identity and access service: members, roles and their
permissions, the last owner kept, teams, units in a tree whose roles hold below them, invitations, joining by a verified
domain and acting on behalf of an organization.**

phil/organizations-bundle keeps organizations and their members. A member is named by the opaque subject identifier of
the application's user provider — a ULID, a UUID, the `sub` of an OpenID Connect provider —, never an email address,
so the bundle works with any user provider. Each membership holds one or more **roles**: `owner`, `admin` and
`member` are built in, and the configuration declares others and the permissions of each — permissions are
configuration, the roles each member holds are data. A member who manages members grants, changes or removes only roles
whose permissions they hold themselves, and **the last owner can never leave, be removed or lose the owner role**, so
an organization always has someone who may grant and revoke; at least two owners are recommended, and a command lists
the organizations below that.

An organization has **teams**, each with roles of its own — `maintainer` and `member` built in, others declared, and
**units** — departments, services, branches — in a tree: created, renamed, moved with the units below them, a cycle
refused, archived and restored. A **unit role** — `manager` and `member` built in, others declared — holds in its unit
and in every unit below it; a member may belong to one unit of an organization only, when the configuration says so.
`UnitTree` answers what other modules ask of the tree, and the bundle draws the administration pages of the units with
the recipes of the PHIL UX kit. It is
active or **archived**, read only until it is restored. Members join by being added, by **invitations** limited in time
and in uses that carry roles — their tokens kept only as a keyed hash, each use counted by one conditional write whose
row count is checked —, or, when the configuration turns it on, by the **email domain** the organization verified. A
member may **act on behalf of the organization**: the organization is shown as the author, and an event records the
real one beside it, for an audit trail.

Every change runs in one transaction and its **events are dispatched once it is committed**, with identifiers only.
`OrganizationVoter` answers `is_granted('organization:<permission>', $organization)`,
`is_granted('organization_role:<role>', $organization)`, and `unit:<permission>` and `unit_role:<role>` on a unit. With
`phil/privacy-bundle`, an exporter and an eraser answer a member's access and erasure requests; with
`phil/access-bundle`, memberships, roles and units are published as relations and tuples of
`phil/resource-permissions`, in the partition of the active tenant, a unit role holding in the units below as it does
in `UnitAccess`.

It is a Symfony bundle on Doctrine DBAL, `phil/slug`, `phil/access-links`, PSR-14 and PSR-20; the console, Symfony
Security, Doctrine ORM, Twig, `phil/privacy-bundle` and `phil/access-bundle` are optional. The organizations live in
eight tables on SQLite, MySQL, MariaDB or PostgreSQL, every identifier and key column compared byte for byte, the units
as materialized paths chosen by benchmark, tested on MySQL 8.4 and PostgreSQL 17; a store in memory serves the tests.
Its services keep no state between requests and serve a long-running worker. Besides the pages of the units, the
application draws its screens with the recipes of the PHIL UX kit.

## Status

| Part | State |
|---|---|
| Organizations: name, slug made by `phil/slug` or given, active or archived, verified email domain | Implemented and tested |
| Memberships by opaque subject identifier, built-in and declared roles, permissions from configuration, grants bounded by the actor's own permissions | Implemented and tested |
| The last owner kept, the organization locked during each change; the recommendation of two owners and its check | Implemented and tested |
| Teams with their own roles, managed by the organization's team managers or the team's maintainers | Implemented and tested |
| Units in a tree, sixteen levels and ten thousand units at most: create, rename, move with a cycle refused, archive and restore with the units below; members, one unit per member when configured | Implemented and tested |
| Unit roles from configuration that hold in the unit and below, checked by `UnitAccess` and the voter; `UnitTree` for other modules | Implemented and tested |
| Administration pages of the units on the recipes of the PHIL UX kit, stateless CSRF tokens | Implemented and tested |
| Invitations limited in time and uses, carrying roles, tokens kept hashed under rotating keys, each use atomic, revocable, refused when their creator may no longer grant their roles | Implemented and tested |
| Joining by a verified email domain, off by default | Implemented and tested |
| Acting on behalf of an organization, the real author recorded | Implemented and tested |
| Domain events dispatched after commit, identifiers only | Implemented and tested |
| `OrganizationVoter` for permissions and roles | Implemented and tested |
| Doctrine DBAL store on SQLite, MySQL and PostgreSQL, binary collation on key columns, schema listener; store in memory | Implemented and tested |
| Commands to list, show, check the owners and purge invitations | Implemented and tested |
| Exporter and eraser for `phil/privacy-bundle`; relations and tuples for `phil/access-bundle`, partitioned by tenant, units and their inherited roles included | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the key of the invitations, the tables, the pages. |
| [Upgrading](doc/upgrading.md) | From one version to the next. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Members and roles](doc/members-and-roles.md) | Members, built-in and declared roles, permissions, the bound on grants, owners, archiving. |
| [Teams](doc/teams.md) | Teams, their roles, who manages them. |
| [Units](doc/units.md) | Units in a tree, their members, who changes what, the questions of other modules, how the tree is stored, the pages. |
| [Role inheritance](doc/role-inheritance.md) | The unit roles, what a member holds in a unit and below it, the voter. |
| [Invitations](doc/invitations.md) | Creating, accepting, revoking and purging invitations; joining by a verified domain. |
| [Acting on behalf](doc/on-behalf.md) | Acting as the organization while the real author is recorded. |
| [Events](doc/events.md) | Every event, when it is dispatched and what it carries. |
| [Integrations](doc/integrations.md) | The voter, `phil/privacy-bundle`, `phil/access-bundle`, `phil/audit-bundle`. |
| [Console](doc/console.md) | The four commands. |
| [Security notes](doc/security.md) | What the bundle defends against, and what it leaves to the application. |
| [Extension points](doc/extension-points.md) | The services to replace, the contracts to implement, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, the servers its integration tests read, and how to test an application. |
| [Reference](doc/reference.md) | Every public type and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\OrganizationsBundle\PhilOrganizationsBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_organizations.yaml
phil_organizations:
    invitations:
        hash_key: '%env(ORGANIZATIONS_INVITATION_KEY)%'
    roles:
        billing: { permissions: [billing.read, billing.pay] }
```

```php
$acme = $organizations->create('Acme Guild', MemberId::fromString($user->id));      // its creator owns it
$members->add($acme->id, $owner, MemberId::fromString($other->id), ['admin']);
$issued = $invitations->create($acme->id, $owner, ['member'], maxUses: 5);         // $issued->token, shown once
$invitations->accept($token, MemberId::fromString($newcomer->id));
$sales = $units->create($acme->id, $owner, 'Sales');
$east = $units->create($acme->id, $owner, 'East', $sales->id);
$units->addMember($sales->id, $owner, MemberId::fromString($other->id), ['manager']);   // manages East too
```

```twig
{% if is_granted('organization:members.invite', organization) %}…{% endif %}
{% if is_granted('unit:unit.manage', unit) %}…{% endif %}
```

## Requirements

PHP 8.5 or later with `ext-filter` and `ext-mbstring`, Symfony 8.2 or later, Doctrine DBAL 4.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
