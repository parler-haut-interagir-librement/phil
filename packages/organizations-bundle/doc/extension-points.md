# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## A store

Any service implementing `Phil\OrganizationsBundle\Application\Contract\OrganizationStore`, named in `store`, replaces
the bundle's: organizations on another database API, behind a cache. To keep units it implements
`Application\Contract\UnitStore` too, on the same transactions; without it the units, their pages and `UnitTree` are left
out, and the rest works as before. It keeps the promises the bundle's contract tests check,
`tests/Unit/Infrastructure/StoreTestCase.php`:

- `transactional()` commits the work when it returns and rolls everything back when it throws;
- `organization($id, lock: true)` holds the organization until the transaction ends, so that two changes of one
  organization run one after the other;
- `useInvitation()` counts a use in one atomic step, only while the invitation is neither revoked, used up nor expired,
  and says whether it did;
- `deleteMembership()` also deletes the member's team and unit memberships of that organization, `deleteTeam()` the
  team's memberships;
- a move of a unit rewrites the path of every unit below it, and the units below a unit are those whose path starts
  with its own;
- listings come sorted — organizations by slug, members by identifier, units by path, invitations the newest first —
  and identifiers, slugs, paths and hashes compare byte for byte.

```yaml
phil_organizations:
    store: App\Organizations\CachedStore
```

## Who a signed-in user is

A `Phil\OrganizationsBundle\Application\Contract\MemberOfUser`, named in `member_of_user`, gives the voter the member
a Symfony user is: see [Integrations](integrations.md#the-voter).

## The slugs

Any `Phil\Slug\Slugger`, named in `slugger`, makes the slugs of organizations and teams from their names: one that
drops leading articles, one in a locale. `phil/slug-bundle` declares named sluggers.

## Roles and permissions

The roles and their permissions are configuration — `roles`, `team_roles` and `unit_roles` —, never code: see
[Members and roles](members-and-roles.md#roles-and-permissions) and [Role inheritance](role-inheritance.md).
`RoleCatalog` (the alias of `phil_organizations.roles`), `phil_organizations.team_roles` and
`phil_organizations.unit_roles` are services, for an application that lists them.

## The tree of units

`UnitTree` is the read-only API another module builds on — a catalogue per unit, a report per unit —, and `UnitAccess`
the permission check a module makes in a unit. Their methods are listed in [Units](units.md#questions-of-other-modules).

## The pages of the units

The pages extend `units.layout`, draw the recipes of the kit under `units.kit_subdirectory`, and take their labels from
the `phil_organizations` translation domain, which an application overrides in its own
`translations/phil_organizations.<locale>.*`. An application that draws its own screens leaves the routes out and calls
`Units` and `UnitTree`.

## The events

Every change dispatches an event of `Domain\Event` once committed: a listener sends a welcome, writes an audit trail,
refreshes a cache. See [Events](events.md).

## The access model

With `phil/access-bundle`, the kinds `organization`, `team` and `unit` and their relations are in the access model, and an
application's own `ModelContributor` builds on them. See [Integrations](integrations.md#philaccess-bundle).

## The services

`Organizations`, `Members`, `Teams`, `Units`, `UnitTree`, `UnitAccess`, `Invitations`, `DomainJoin`, `OnBehalf`,
`OwnershipCheck`, `MemberErasure`, `OrganizationAccess`, `RoleCatalog`, `OrganizationStore` and `UnitStore` are public
services, autowirable by their class.
