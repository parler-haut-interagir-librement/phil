# Changelog

All notable changes to `phil/terms-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
