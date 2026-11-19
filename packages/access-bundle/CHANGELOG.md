# Changelog

All notable changes to `phil/access-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.1-alpha - 2026-11-18

The next version is 0.2.1-alpha: every tenant can be purged in one run.

### Added

- `phil:access:purge --all-tenants`: each tenant whose partition holds tuples entered in turn through the tenant
  context, purged and left, even when its purge fails; a report per tenant, and exit `1` when one failed. Refused
  when the tuples are not partitioned by tenant, with `--tenant`, or without a list of partitions.
  `Application\Contract\TuplePartitions`, the partitions that hold tuples, and `Infrastructure\Dbal\DbalTuplePartitions`,
  its default with the DBAL store.
- The documentation of purging every tenant.

### Changed

- The README says that organizations and their roles come from `phil/organizations-bundle`, now published.

## 0.2.0-alpha - 2026-11-17

The next version is 0.2.0-alpha: "view as this member" stays within the admin's space and is read-only for the whole
application, and the tuples are partitioned by tenant. See [Upgrading from 0.1](doc/tenants.md#upgrading-from-01).

### Added

- The option `partition` — `auto`, `tenant` or `single` — and `Infrastructure\Tenancy\TenancyTupleScope`, the active
  tenant of `phil/tenancy-bundle` as the partition of the tuples, failing closed outside a tenant.
- `Infrastructure\Http\ViewAsGuard`, on `kernel.controller`: during a view, a request whose method is not safe is
  answered with 403 and logged; the attributes `#[AllowedDuringViewAs]` and `#[RefusedDuringViewAs]`; the option
  `view_as.read_only`, true by default.
- `ViewAs::refuseWhileViewing()`, for a change outside a controller.
- `ViewAsRefused::notAMemberOfTheSpace()` and `ViewAsRefused::readOnly()`.
- `Domain\Status\ViewAsEnd`, and `ViewAsStopped::$reason`.
- The documentation of tenants, of the boundary of "view as this member" and of the upgrade from 0.1.

### Changed

- `ViewAs::start()` refuses a member who is not a member of the space the admin administers, and logs the refusal by
  identifiers.
- `ViewAs::member()` ends a view whose admin is no longer an admin of the space or no longer signed in, or whose member
  left the space: it removes the member from the session, logs the end and dispatches `ViewAsStopped` with its reason.
- `phil:access:purge` purges the tuples of the command's tenant, and fails without one when the tuples are partitioned.
- The bundle requires `phil/resource-permissions` `^0.2@dev`.

### Security

- An admin of one space, or of one tenant, can no longer view the site as a member of another.
- A view masked after the admin lost the right is no longer brought back when the right comes back.
- During a view, the admin's own roles and the voters of the application no longer let a change through unnoticed:
  the guard refuses it.

## 0.1.0-alpha - 2026-11-13

### Added

- The access model on `phil/resource-permissions`: spaces with admins and members, access groups, and per item kind an
  owner, a space, a visibility marker and one relation per sharing level, each including the next; extended by
  services tagged `phil_access.model_contributor`.
- `MemberId`: a member by a stable identifier, refusing an email address; `TokenCurrentMember` reads it from the
  security token, `NoCurrentMember` without SecurityBundle, `CurrentMember` for the application's own.
- `Items`: owner, space and visibility — everyone, members, admins, owner, private by default —, transfer and forget;
  `Visibility::linking()` for the narrowest of two.
- `Sharing`: shares with members and access groups at a sharing level, ending on their own at a given second; the list
  of an item's shares.
- `AccessGroups`: declared groups, their entry points — purchase, invitation, bulk, automation, default —, memberships
  that end on their own after the group's duration, `MemberJoinedGroup` and `MemberLeftGroup`.
- `Spaces`: the members and admins of the site's space or of another, members or groups.
- `ViewAs`: "view as this member" for admins, read-only, kept in the session, logged and dispatched as `ViewAsStarted`
  and `ViewAsStopped`; `ViewAsController` and its routes, behind a stateless CSRF token, going back to a configured path.
- `ItemAccess`: checks, explanations and listings for the request's subject, refusing and logging past the budget.
- `AccessVoter` for `access.<level>` and `access.view_as`; `EasyAdminAccessVoter` for `EA_ACCESS_ENTITY` and
  `EA_EXECUTE_ACTION`, with the levels of EasyAdmin's actions configurable.
- The Twig functions `access_can`, `access_visibility` and `access_viewing_as`; the components
  `PhilAccess:ViewAsBanner` and `PhilAccess:AccessSummary`, drawn with the recipes of the PHIL UX kit, in English and
  French.
- The configuration `phil_access`: store, connection, table, space, item kinds, sharing levels, default visibility,
  groups, budget, current member, redirect of "view as", EasyAdmin actions, kit subdirectory.
- The command `phil:access:purge`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
