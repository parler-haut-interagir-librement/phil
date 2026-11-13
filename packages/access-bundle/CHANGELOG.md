# Changelog

All notable changes to `phil/access-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
