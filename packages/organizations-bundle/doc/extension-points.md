# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## A store

Any service implementing `Phil\OrganizationsBundle\Application\Contract\OrganizationStore`, named in `store`, replaces
the bundle's: organizations on another database API, behind a cache. It keeps the promises the bundle's contract tests
check, `tests/Unit/Infrastructure/StoreTestCase.php`:

- `transactional()` commits the work when it returns and rolls everything back when it throws;
- `organization($id, lock: true)` holds the organization until the transaction ends, so that two changes of one
  organization run one after the other;
- `useInvitation()` counts a use in one atomic step, only while the invitation is neither revoked, used up nor expired,
  and says whether it did;
- `deleteMembership()` also deletes the member's team memberships of that organization, `deleteTeam()` the team's
  memberships;
- listings come sorted — organizations by slug, members by identifier, invitations the newest first — and identifiers,
  slugs and hashes compare byte for byte.

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

The roles and their permissions are configuration — `roles` and `team_roles` —, never code: see
[Members and roles](members-and-roles.md#roles-and-permissions). `RoleCatalog` (the alias of
`phil_organizations.roles`) and `phil_organizations.team_roles` are services, for an application that lists them.

## The events

Every change dispatches an event of `Domain\Event` once committed: a listener sends a welcome, writes an audit trail,
refreshes a cache. See [Events](events.md).

## The access model

With `phil/access-bundle`, the kinds `organization` and `team` and their relations are in the access model, and an
application's own `ModelContributor` builds on them. See [Integrations](integrations.md#philaccess-bundle).

## The services

`Organizations`, `Members`, `Teams`, `Invitations`, `DomainJoin`, `OnBehalf`, `OwnershipCheck`, `MemberErasure`,
`OrganizationAccess`, `RoleCatalog` and `OrganizationStore` are public services, autowirable by their class.
