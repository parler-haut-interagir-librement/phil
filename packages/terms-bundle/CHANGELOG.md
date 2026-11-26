# Changelog

All notable changes to `phil/terms-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.1-alpha - 2026-11-25

### Changed

- The development requirements take `twig/html-extra` 3.31.1 or later, which fixes the advisories of its sandbox
  published on 2026-11-24.
- The development requirements accept `phil/notifications-bundle` 0.4, the minor version of its main branch and no
  earlier one, so that the package installs on its own beside that bundle.
- The development requirements take `easycorp/easyadmin-bundle` 5.10.4 or later, the release that fixes
  GHSA-f7rw-7wx5-3c54: a link to a route, like the menu links of this bundle, passed its `routeParams` to the controller
  of that route as arguments.

### Security

- Requires `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on 2026-11-24.
- Conflicts with `easycorp/easyadmin-bundle` below 5.10.4, the release that fixes GHSA-f7rw-7wx5-3c54: an application
  cannot install this bundle next to a vulnerable EasyAdmin, which stays optional.

## 0.3.0-alpha - 2026-11-24

Each acceptance proves the text accepted, with two nullable columns of `phil_terms_acceptance`. See [Upgrading from
0.2](doc/installation.md#upgrading-from-02).

### Added

- `Acceptance` keeps `locale`, the locale whose text the member was shown, and `textSha256`, the SHA-256 of the HTML
  `PolicyTexts::toHtml()` renders from that text; `phil_terms_acceptance` keeps them in `locale` and `text_sha256`,
  both nullable for the acceptances recorded before them. `Terms::accept()` takes the locale shown as a last argument
  — the acceptance page and `SignupAcceptances::record()` give the request's —, falls back to `default_locale`, then
  to the first locale of the version, and `Terms::textMatches()` checks the proof against the version kept.
  `PolicyVersion::textLocale()` names the locale whose text `text()` gives. The export of `phil/privacy-bundle` lists
  both. The [acceptance guide](doc/acceptance.md#proving-the-text-accepted) says how to prove the text accepted.

### Changed

- The development requirements accept `phil/notifications-bundle` 0.3 and `phil/privacy-bundle` 0.3, the minor versions
  of their main branches and no earlier one, so that the package installs on its own beside those bundles.
- Requires the latest minor of each dependency (`doctrine/dbal` 4.6, `twig/twig` 3.31, `symfony/ux-twig-component`
  3.8, `symfony/service-contracts` 3.8, …), and of each development requirement (`doctrine/orm` 3.8,
  `doctrine/doctrine-bundle` 3.4, `easycorp/easyadmin-bundle` 5.10, `symfony/ux-toolkit` 3.8, …). The dates and
  booleans of a row are read by the DBAL types themselves instead of the static `Type::getType()`, which DBAL 4.6
  deprecates.

## 0.2.0-alpha - 2026-11-18

Members and policies compare byte by byte on MySQL and MariaDB. See [Upgrading from
0.1](doc/installation.md#upgrading-from-01).

### Changed

- `TermsTables::configure()` takes the platform of the connection as its second argument, and the schema listener
  gives it: on MySQL and MariaDB, `policy` of `phil_terms_version` and `member` and `policy` of
  `phil_terms_acceptance` take the `utf8mb4` character set and the `utf8mb4_bin` collation. PostgreSQL and SQLite keep
  their default, which compares bytes already.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, an acceptance by the member `Ann` let `ann`
  through the acceptance gate without accepting the policy, and the export of a member's data held the acceptances of
  a member whose identifier differed only by case.

## 0.1.0-alpha - 2026-11-15

### Added

- `Policy`: a key, whether its version in force must be accepted to go on, the days after which an acceptance is
  asked again, and whether its versions name the licence of the contributions; `Policies`, in the order of the
  configuration.
- `PolicyVersion`: a number, the text of up to 32 locales of 256 KiB each, a `TextFormat` (plain text or UJO
  (Universal JSON Output)), the moments of publication and of effect, and the material flag, the first version always
  material, and the SPDX identifier of the contribution licence when its policy names one; never changed once built.
- `Acceptance`: a member, a version, a moment, an `AcceptanceChannel` (signup, form, API, import) and, optionally, a
  network; `Identifiers`, the shapes of the policy keys, member identifiers, locales and licences; `InvalidTerms`,
  whose reason is a translation key.
- The `PolicyVersionRepository` and `AcceptanceRepository` ports and their DBAL adapters on `phil_terms_version` and
  `phil_terms_acceptance`, added to the schema the ORM generates by `TermsSchemaListener` when the ORM is installed;
  `TermsTables` and `RowReader`. Every read and write goes through DBAL; the moments are written and read back in UTC
  (`datetime_utc_immutable`), whatever the time zone of the server.
- `VersionCatalog`: the versions of each policy read once per request and dropped on `kernel.reset`, with the version
  in force, the version required and the latest material version at a moment.
- `Terms`: `publish()`, numbered after the latest version and checked in its format, and `accept()`, of the version in
  force or a later one; the `VersionPublished` and `TermsAccepted` events.
- `ContributionLicences`, the licence a member's contribution was published under at a moment: the highest version
  of the licensing policy the member had accepted and that was in force, as a `ContributionLicence`; the licence field
  of the administration and the licence shown on the public and acceptance pages.
- `AcceptanceChecker`, what a member has to accept at a moment, renewal included; `Outstanding`, the members not in
  order with a policy, read 500 at a time from the `MemberSource` port the application names.
- The `MemberResolver` port and `TokenMemberResolver`, the user identifier of the security token, built only when
  `stable_identifiers` declares that it is never an email address, and refused when it is shaped like one.
- `AcceptanceGate` on `kernel.request`: a page sent to the acceptance page, an API answered `403` with a problem as
  `application/problem+json`, the bundle's routes and the exempt routes and paths let through.
- `AcceptanceController`, the acceptance page with one checkbox per version, never ticked in advance; `Pages`, the
  templates, the CSRF token and the local address to come back to.
- `PolicyController`: the version in force, each version at its own address, and the changes from one version to
  another; the `PolicyTexts` port and `PolicyTextRenderer`, plain text escaped in paragraphs, UJO through `phil/ujo`,
  changes through `phil/text-diff`.
- `TermsAdminController`, the policies with their versions, their moments shown in the time zone of the
  administration, and the form that publishes one; `TermsAdmin`, the link of an EasyAdmin menu.
- `TermsAcceptanceTypeExtension`, the `terms_acceptance` option of a form, and `SignupAcceptances`, which keeps the
  acceptances of a valid signup form.
- The `TermsNotifier` port, `NotificationsNotifier` and `OutstandingAudience` over `phil/notifications-bundle`; the
  `SendTermsReminders` message, its handler and `TermsSchedule`.
- The types `phil_terms.published` and `phil_terms.reminder` declared to `phil/notifications-bundle` by the bundle, with
  their title and body in English and French.
- The `AddressMasker` port and `NetworkMasker` over `phil/ip-anonymizer`; `AcceptancesPrivacy`, the export and the
  erasure of `phil/privacy-bundle`.
- `phil:terms:outstanding`; `phil:terms:check-legal-links`, the check that the footer links of
  `phil/legal-notices-bundle` exist and that the layout of the terms pages shows them.
- The routes of the pages and of the administration, their Twig templates, and the messages of the `phil_terms`
  domain in English and French. The templates draw their parts with the recipes of the PHIL UX kit or the Shadcn kit
  of the Symfony UX Toolkit (`PhilTermsBundle::RECIPES`), called by the names of `phil_terms_kit()`: `TermsTwig`,
  `KitComponents` and `KitSubdirectoryPass`, which reads the components subdirectory of the Toolkit. They pass only the
  props both kits share and write no word of their own.
- The configuration `phil_terms`: `connection`, `clock`, `default_locale`, `locales`, `timezone`, `kit_subdirectory`, `policies`,
  `member_resolver`, `stable_identifiers`, `member_source` (required), `notifier`, `gate`, `network`, `pages`,
  `admin`, `notifications` and `reminders`; `policies.<key>.contribution_licence`.
- The benchmark of the gate.
- The Flex recipe in `recipe/0.1`: the bundle registered, `config/packages/phil_terms.yaml` with two policies and
  `config/routes/phil_terms.yaml` with the public pages, and `App\Terms\Members` and `App\Terms\CurrentMember`, which
  name nobody until the application completes them.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
