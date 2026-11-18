# Changelog

All notable changes to `phil/organizations-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
