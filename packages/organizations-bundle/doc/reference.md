# Reference

Every public type of the package, with its role.

## Domain

| Type | Role |
|---|---|
| `Domain\Model\Organization` | An organization: identifier, name, slug, status, creation, archiving, verified domain. |
| `Domain\Model\Membership` | One member of one organization, with roles, how and when they joined. |
| `Domain\Model\Team` | A team of an organization: identifier, name, slug, creation. |
| `Domain\Model\TeamMembership` | One member of one team, with team roles. |
| `Domain\Model\Unit` | A unit of an organization: identifier, name, path, status, creation, archiving; `moveUnder()`. |
| `Domain\Model\UnitMembership` | One member of one unit, with unit roles. |
| `Domain\Model\Invitation` | An invitation: roles, uses allowed and made, creation, expiry, creator, revocation; its state at a moment. |
| `Domain\ValueObject\UnitPath` | Where a unit stands: its ancestors from the root, then itself, written `/root/unit/`, sixteen levels at most; the bound of its descendants. |
| `Domain\ValueObject\OrganizationId`, `TeamId`, `UnitId`, `InvitationId` | Identifiers: a UUID version 7 when the bundle creates one, or the application's. |
| `Domain\ValueObject\MemberId` | A member by the opaque subject identifier of the user provider. |
| `Domain\ValueObject\Identifier` | The rule of every identifier: 1 to 128 ASCII letters, digits, `_`, `.` or `-`, never `@`. |
| `Domain\ValueObject\Roles` | One to sixteen role names, sorted, without repeats. |
| `Domain\ValueObject\OrganizationName` | A name of an organization, a team or a unit: trimmed, 1 to 200 characters, no control character. |
| `Domain\ValueObject\EmailDomain` | A verified email domain, in lowercase ASCII; the domain of an address. |
| `Domain\ValueObject\Moment` | The moments of the model, to the second and in UTC. |
| `Domain\Status\OrganizationStatus` | `active` or `archived`, of an organization or a unit. |
| `Domain\Status\JoinMethod` | `created`, `added`, `invitation` or `domain`. |
| `Domain\Status\LeaveReason` | `left`, `removed` or `erased`. |
| `Domain\Status\InvitationState` | `usable`, `expired`, `revoked` or `used_up`. |
| `Domain\Event\*` | The events, listed in [Events](events.md). |
| `Domain\Exception\OrganizationsException` | What every exception of the bundle implements. |
| `Domain\Exception\InvalidOrganization` | A value the model refuses. |

## Application

| Type | Role |
|---|---|
| `Application\Organizations` | Creates, finds, lists, renames, archives and restores organizations; records their verified domain. |
| `Application\Members` | Adds members, changes their roles, removes them; a member leaves; the last owner kept. |
| `Application\Teams` | Creates, renames and deletes teams; adds members, changes their team roles, removes them. |
| `Application\Units` | Creates, renames, moves, archives and restores units; adds members, changes their unit roles, removes them. |
| `Application\UnitTree` | The questions of other modules: a unit, its ancestors, children, descendants and members, the units of a member. |
| `Application\UnitAccess` | What a member may do in a unit, the unit roles above it included: `can()`, `hasRole()`, `permissions()`. |
| `Application\Invitations` | Creates, previews, accepts, revokes, lists and purges invitations. |
| `Application\IssuedInvitation` | An invitation newly created, and its token, shown once. |
| `Application\InvitationPolicy` | The lifetimes and uses of invitations. |
| `Application\DomainJoin` | Joining an organization by its verified email domain. |
| `Application\OnBehalf`, `OnBehalfContext` | Acting on behalf of an organization, the real author recorded. |
| `Application\OrganizationAccess` | What a member may do in an organization: `can()`, `hasRole()`, `permissions()`. |
| `Application\RoleCatalog` | The roles and their permissions, of organizations, teams or units; `covers()`, the bound on grants. |
| `Application\Permission` | The built-in permissions. |
| `Application\OwnershipCheck` | The recommendation of at least two owners, and the organizations below it. |
| `Application\MemberErasure` | Erases a member from every organization. |
| `Application\UnitOfWork`, `EventRecorder` | A change in one transaction, its events dispatched after commit. |
| `Application\SlugText` | The slugs of organizations and teams, given or made. |
| `Application\Contract\OrganizationStore` | Where organizations, members, teams and invitations are kept. |
| `Application\Contract\UnitStore` | Where units and their members are kept, beside the organizations. |
| `Application\Contract\MemberOfUser` | The member a signed-in user is. |
| `Application\Exception\*` | `UnknownOrganization`, `UnknownTeam`, `UnknownUnit`, `UnknownInvitation`, `UnknownRole`, `NotAMember`, `AlreadyMember`, `LastOwner`, `PermissionDenied`, `OrganizationArchived`, `SlugTaken`, `UnitArchived`, `UnitCycle`, `InvitationNotUsable` and its `reason`, `DomainJoinRefused`. |

## Infrastructure

| Type | Role |
|---|---|
| `Infrastructure\Dbal\DbalOrganizationStore` | The store of organizations and units on a Doctrine DBAL connection; `configureSchema()`. |
| `Infrastructure\Dbal\OrganizationTables` | The eight tables, built for a platform; `upgrade()` and `downgrade()` for a migration. |
| `Infrastructure\Dbal\DbalRows` | The rows read back as the model. |
| `Infrastructure\Doctrine\OrganizationSchemaListener` | Adds the tables to the schema the ORM generates. |
| `Infrastructure\Memory\InMemoryOrganizationStore` | The store in memory, reset between requests. |
| `Infrastructure\Security\OrganizationVoter` | `organization:<permission>`, `organization_role:<role>`, `unit:<permission>` and `unit_role:<role>`. |
| `Infrastructure\Http\UnitAdminController` | The administration pages of the units. |
| `Infrastructure\Twig\KitComponents`, `OrganizationsExtension` | The names of the recipes of the kit, and `phil_organizations_kit()`. |
| `Infrastructure\DependencyInjection\UnitServicesPass`, `KitSubdirectoryPass` | The units left out of a store that keeps none, the pages of an application without Twig; the components subdirectory of the Toolkit. |
| `Infrastructure\Security\UserIdentifierMember` | The user identifier as the member, when it is a stable identifier. |
| `Infrastructure\Console\ListOrganizationsCommand`, `ShowOrganizationCommand`, `CheckOwnersCommand`, `PurgeInvitationsCommand` | The commands of [Console](console.md). |
| `Infrastructure\Privacy\OrganizationsPrivacy` | The exporter and eraser of `phil/privacy-bundle`. |
| `Infrastructure\Access\OrganizationRelations` | The kinds and relations added to the access model of `phil/access-bundle`: organizations, teams and, with unit roles, units whose roles hold below them. |
| `Infrastructure\Access\MembershipTuples` | Memberships, roles, units and their parents published as tuples. |
| `Infrastructure\DependencyInjection\OrganizationsServices` | The services, from the configuration. |
| `PhilOrganizationsBundle` | The bundle: its configuration `phil_organizations` and its services. |

## The tables

| Table | One row per | Keys |
|---|---|---|
| `phil_organization` | organization | `id`; `slug` unique; `email_domain` |
| `phil_organization_member` | member of an organization, with their roles | `organization_id, member_id`; `member_id` |
| `phil_organization_member_role` | role of a member, what owners are counted from | `organization_id, role, member_id`; `member_id` |
| `phil_organization_team` | team | `id`; `organization_id, slug` unique |
| `phil_organization_team_member` | member of a team, with their team roles | `team_id, member_id`; `member_id, organization_id` |
| `phil_organization_unit` | unit, with its parent, path and depth | `id`; `organization_id, path`; `organization_id, parent_id` |
| `phil_organization_unit_member` | member of a unit, with their unit roles | `unit_id, member_id`; `member_id, organization_id` |
| `phil_organization_invitation` | invitation | `id`; `token_hash` unique; `organization_id, created_at`; `created_by`; `expires_at`; `revoked_at` |

Identifiers, slugs, paths, roles, domains and hashes are ASCII columns in `ascii_bin` on MySQL and MariaDB, in the `C`
collation on PostgreSQL; moments are Unix timestamps.
