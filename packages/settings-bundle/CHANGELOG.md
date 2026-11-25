# Changelog

All notable changes to `phil/settings-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.2.2-alpha.

### Security

- Requires `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on 2026-11-24.

## 0.2.1-alpha - 2026-11-24

### Changed

- The Node tests resolve `@hotwired/stimulus` to their stub with a synchronous hook given to `module.registerHooks()`,
  on the loading thread, instead of `module.register()`, which Node deprecates (DEP0205).
- The development requirements accept `phil/notifications-bundle` 0.3 and `phil/privacy-bundle` 0.3, the minor versions
  of their main branches and no earlier one, so that the package installs on its own beside those bundles.
- Requires the latest minor of each dependency (`doctrine/dbal` 4.6, `twig/twig` 3.31, `symfony/service-contracts`
  3.8, `symfony/translation-contracts` 3.8), and of each development requirement (`doctrine/orm` 3.8,
  `doctrine/doctrine-bundle` 3.4).

## 0.2.0-alpha - 2026-11-18

The scopes, subjects, keys and versions of the two tables compare byte by byte on
MySQL and MariaDB. See [Upgrading from 0.1](doc/storage.md#upgrading-from-01).

### Changed

- `SettingTables::configure()` takes the platform of the connection as its second argument, and the schema listener
  gives it: on MySQL and MariaDB, `scope`, `subject` and `setting_key` of `phil_settings` and `version` of
  `phil_settings_migrations` take the `utf8mb4` character set and the `utf8mb4_bin` collation. PostgreSQL and SQLite
  keep their default, which compares bytes already.

### Fixed

- Under the default case-insensitive collation of MySQL and MariaDB, the users `Ann` and `ann` shared one row per key:
  each read and overwrote the other's settings.

## 0.1.0-alpha - 2026-11-14

### Added

- `Domain\Setting\SettingDefinition`: a setting's key, type (`SettingType`: boolean, integer, text, choice, choices),
  default, rules — bounds, length, pattern, up to 100 choices —, scope (`Scope`: user or site), section, translation
  keys, role, whether visitors keep it, its root attribute and its order; it checks a value, with a reason a form
  translates (`InvalidSettingValue`), and encodes and decodes it as JSON, a stored value it no longer accepts read as
  null. `SectionDefinition`, `Keys` and `Ordering`, which places declarations by `before` and `after`.
- `Domain\ValueObject\Subject`, a user by identifier or the site; `Domain\Contract\SettingRepository`; the exceptions
  `SettingsException`, `InvalidSetting` and `UnknownSetting`.
- `Application\SettingCatalog`, the declarations in their order, refusing a key twice, a section missing or a circle,
  with the settings of the root element kept apart.
- `Application\Settings`: typed reading with the default — the site's value, a user's, the current user's, or a
  visitor's —, previewing what a save would do (`SettingPreview`), saving only what differs from the default after
  checking every value, restoring defaults, forgetting a subject, one read per subject and request, each value decoded
  once, and a reset between requests; `Event\SettingsChanged`, made of identifiers.
- Migrations of the stored values: `Domain\Contract\SettingMigration` declared with `#[AsSettingMigration]` and its
  version, ordered by `before` and `after`; the steps `Step::rename()`, `convert()`, `defaultChanged()` and `remove()`;
  `Application\Migration\SettingMigrator`, which applies each pending migration once in one transaction with its
  version, or tries them on a dry run; the port `SettingMigrationStore`; the command `phil:settings:migrate` and its
  `--dry-run`; `SettingMigrationsPass`, which refuses a version malformed or given twice.
- `Application\BuiltInSettings`: the sections general, notifications, privacy and display; the display preferences
  — theme, motion, contrast, text size —, kept for visitors and written on the root element; the notification
  preferences — digest, email, Web Push, quiet hours.
- `Application\Visitor\PreferenceCookieFormat`, the bounded and lenient cookie of a visitor's preferences, with its fuzz
  target; `Application\DocumentAttributes`, the root element's attributes; the ports `CurrentUser` and
  `VisitorPreferences`.
- `Infrastructure\Doctrine\DbalSettingRepository` on the table `phil_settings`, one row per subject and key, and the
  table `phil_settings_migrations` of the versions applied, added to the ORM's schema by `SettingSchemaListener`;
  `Infrastructure\Memory\InMemorySettingRepository` for tests.
- `Infrastructure\Http\SettingsController`: the preferences and the site's settings, a section per page, shown by
  scope, visitor and role, previewed without saving, saved and redirected, refused in a `422`;
  `Http\PreferenceCookie`, strictly necessary, HTTP-only and `SameSite=Lax`; `Form\SettingsForm`, a classless form
  per section, its errors translated, its CSRF token stateless; the templates, the routes, and the texts in English
  and French.
- `Infrastructure\Twig\SettingsExtension`: `phil_setting()` and `phil_document_attributes()`; the Stimulus controller
  `document`, which applies a display preference as soon as it is chosen.
- `Infrastructure\Notifications\SettingsNotificationPreferences`, the notifications bundle's preferences, set as that
  bundle's when it is installed; `Infrastructure\Privacy\SettingsPrivacy`, the privacy bundle's export and erasure.
- `PhilSettingsBundle` and its configuration `phil_settings`; `#[AsSetting]` and `#[AsSettingSection]`, read by
  `DeclaredSettingsPass`, which builds the catalog when the container is built and refuses forms without their CSRF
  protection.
- The benchmarks of the root element's attributes and of the cookie; a functional suite with SecurityBundle, signing in
  by HTTP Basic, for the site's settings and their roles.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The Flex recipe in `recipe/0.1`: the bundle registered, `config/packages/phil_settings.yaml` with the role of the
  site's settings and `config/routes/phil_settings.yaml` with the preferences and the site's pages.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
