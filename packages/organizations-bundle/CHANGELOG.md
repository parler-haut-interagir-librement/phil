# Changelog

All notable changes to `phil/organizations-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.2.1-alpha.

### Changed

- The development requirements accept `phil/privacy-bundle` 0.3, the minor version of its main branch and no earlier
  one, so that the package installs on its own beside that bundle.
- Requires the latest minor of each dependency (`doctrine/dbal` 4.6, `phil/access-links` 0.2,
  `symfony/service-contracts` 3.8), and of each development requirement (`doctrine/orm` 3.8,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, `symfony/ux-twig-component` 3.8, …).
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.

## 0.2.0-alpha - 2026-11-20

The units of an organization, in a tree whose roles hold below them. A minor version and
not a patch: two tables join the six of 0.1, `units.manage` joins the built-in permissions of owners and admins, and a
store of the application's own implements `UnitStore` to keep units. Run the migration the recipe copies, or one that
calls `OrganizationTables::upgrade()`. See [Upgrading](doc/upgrading.md#from-01-to-02).

### Added

- `Domain\Model\Unit`, `UnitMembership`, `Domain\ValueObject\UnitId` and `UnitPath`: units of an organization in a
  tree, sixteen levels deep at most, ten thousand per organization at most, their path the identifiers of their
  ancestors and their own, the bound of their descendants one range of an index; their members with unit roles.
- `Application\Units`: create, rename, move with every unit below — a move under the unit itself or below it refused
  by `UnitCycle` —, archive and restore with the units below — `UnitArchived` for an archived unit —; add members,
  change their unit roles, remove them, a member leaving; one unit per member of an organization with
  `units.single_membership`; each change locking its organization. `UnknownUnit`, and `AlreadyMember::ofUnit()`,
  `ofAnotherUnit()` and `NotAMember::ofUnit()`.
- `RoleCatalog::unit()`, the unit roles — `manager` and `member` built in, others from `unit_roles` —;
  `Permission::UNITS` (`units.manage`, held by owners and admins) and `Permission::UNIT_MANAGE` (`unit.manage`).
- `Application\UnitAccess`: the permissions and roles of a member in a unit, those granted above it included, every
  unit permission for `units.manage`, nothing in an archived unit or organization.
- `Application\UnitTree`: the questions of other modules — a unit, the roots, children, ancestors and descendants,
  whether a unit is below another, its members and the members below it, the units of a member and those above them.
- `Application\Contract\UnitStore`, implemented by `DbalOrganizationStore` and `InMemoryOrganizationStore`; the tables
  `phil_organization_unit` and `phil_organization_unit_member`; `OrganizationTables::upgrade()` and `downgrade()` for a
  migration; a member who leaves an organization leaves its units.
- The events `UnitCreated`, `UnitRenamed`, `UnitMoved`, `UnitArchived`, `UnitRestored`, `UnitMemberAdded`,
  `UnitMemberRolesChanged` and `UnitMemberRemoved`, identifiers and role names only; `UnitMemberRemoved` when a member
  leaves the organization or is erased.
- `unit:<permission>` and `unit_role:<role>` in `OrganizationVoter`.
- `Infrastructure\Http\UnitAdminController`, its routes `config/routes/units.php` and templates: the tree of an
  organization and the page of a unit, on the recipes `Badge`, `Button`, `Input`, `NativeSelect`, `Table` and `Tree` of
  the PHIL UX kit, with the stateless CSRF token `phil_organizations_units`; the translation domain
  `phil_organizations` in English and French; `KitComponents`, `OrganizationsExtension` and `KitSubdirectoryPass`;
  `UnitServicesPass`, which leaves the units out of a store that keeps none and the pages out of an application
  without Twig.
- The configuration `unit_roles` and `units` — `single_membership`, `layout`, `kit_subdirectory`.
- The unit memberships in the export and erasure of `OrganizationsPrivacy`.
- The Flex recipe `recipe/0.2`, with the routes of the pages and the migration of the tables of the units.
- The benchmark of the tree of units, and the comparison of materialized paths with a closure table on MySQL and
  PostgreSQL in `doc/units.md`; `doc/role-inheritance.md` and `doc/upgrading.md`.

## 0.1.0-alpha - 2026-11-18

### Added

- `Domain\ValueObject\Identifier`, `OrganizationId`, `TeamId`, `InvitationId` and `MemberId`: identifiers of 1 to 128
  ASCII letters, digits, `_`, `.` or `-`, never an email address, UUIDs version 7 when the bundle creates them; a member
  named by the opaque subject identifier of any user provider. `Roles`, one to sixteen role names sorted without
  repeats; `OrganizationName`; `EmailDomain`, a verified domain in lowercase ASCII; `Moment`, to the second in UTC;
  `InvalidOrganization` and `OrganizationsException`.
- `Domain\Model\Organization` — name, slug, active or archived, verified domain —, `Membership`, `Team`,
  `TeamMembership` and `Invitation` — roles, uses allowed and made, expiry, creator, revocation, its state at a
  moment —; the statuses `OrganizationStatus`, `JoinMethod`, `LeaveReason` and `InvitationState`.
- The events of `Domain\Event`, identifiers and role names only: organizations created, renamed, archived, restored,
  their domain changed, left without an owner; members joined, their roles changed, left; teams created and deleted,
  their members added, changed and removed; invitations created, accepted and revoked; `ActedOnBehalf`.
- `Application\RoleCatalog`: `owner`, `admin` and `member` built in, `maintainer` and `member` for teams, other roles and
  permissions from configuration, the owner holding every permission; `covers()`, the bound on what a member grants.
  `Application\Permission`, the built-in permissions. `Application\OrganizationAccess`: `can()`, `hasRole()`,
  `permissions()`, an archived organization read only.
- `Application\UnitOfWork` and `EventRecorder`: each change in one transaction of the store, its events dispatched
  through PSR-14 once committed and never after a rollback.
- `Application\Organizations`: create — its creator the first owner, the slug made by `phil/slug` and numbered when
  taken, or given and checked —, find by identifier or slug, list, rename, archive, restore, record a verified domain;
  `SlugText`.
- `Application\Members`: add, change roles, remove, leave, bounded by the actor's own permissions; the last owner never
  leaves, is removed or loses the owner role, each change locking its organization. `Application\OwnershipCheck`, the
  recommendation of at least two owners.
- `Application\Teams`: teams with their own roles, managed by the organization's team managers or the team's
  maintainers within their team roles; members of the organization only.
- `Application\Invitations`, `IssuedInvitation` and `InvitationPolicy`: invitations limited in time and uses carrying
  roles, their tokens those of `phil/access-links` kept only as an HMAC-SHA-256 under rotating keys, previewed,
  accepted with each use one conditional `UPDATE` whose row count is checked, refused with one message and a `reason`
  when unknown, expired, revoked, used up, of an archived organization or when their creator may no longer grant their
  roles; revoked, listed, purged.
- `Application\DomainJoin`: joining by an organization's verified email domain, off by default, with the configured
  roles and never `owner`; the address read for its domain only.
- `Application\OnBehalf` and `OnBehalfContext`: acting on behalf of an organization, the real author recorded by
  `ActedOnBehalf`, the context readable during the call and reset between requests.
- `Application\MemberErasure`: a member erased from every organization, the last owner too with
  `OrganizationLeftWithoutOwner`, their pending invitations revoked and their name forgotten on the others.
- `Application\Contract\OrganizationStore` and `MemberOfUser`; the exceptions of `Application\Exception`.
- `Infrastructure\Dbal\DbalOrganizationStore`, `OrganizationTables` and `DbalRows`: six tables on SQLite, MySQL,
  MariaDB and PostgreSQL, every identifier and key column in `ascii_bin` or the `C` collation, the organization read
  `FOR UPDATE` during a change, tested on MySQL 8.4 and PostgreSQL 17; `Infrastructure\Doctrine\OrganizationSchemaListener`
  for the schema of the ORM; `Infrastructure\Memory\InMemoryOrganizationStore`.
- `Infrastructure\Security\OrganizationVoter` — `organization:<permission>`, `organization_role:<role>` — and
  `UserIdentifierMember`.
- The commands `phil:organizations:list`, `show`, `check-owners` and `purge-invitations`.
- `Infrastructure\Privacy\OrganizationsPrivacy`, the exporter and eraser of `phil/privacy-bundle` when it is installed.
- `Infrastructure\Access\OrganizationRelations` and `MembershipTuples`: the kinds `organization` and `team` in the
  access model of `phil/access-bundle`, memberships and roles published as tuples in the partition of its scope.
- `PhilOrganizationsBundle` and its configuration `phil_organizations`; a Flex recipe.
- The benchmark of a permission check.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
