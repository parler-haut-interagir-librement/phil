# Reference

## Domain

| Class | Role |
|---|---|
| `Domain\Setting\SettingDefinition` | A setting: key, type, default, rules, scope, section, labels, order; checks, encodes and decodes a value |
| `Domain\Setting\SectionDefinition` | A section: key, labels, order |
| `Domain\Setting\SettingType` | `boolean`, `integer`, `text`, `choice`, `choices` |
| `Domain\Setting\Scope` | `user`, `site` |
| `Domain\Setting\Keys` | The shapes of keys, choices and attributes |
| `Domain\Setting\Ordering` | The order of declarations, `before` and `after` |
| `Domain\ValueObject\Subject` | A user, by identifier, or the site |
| `Domain\Contract\SettingRepository` | Where the values are kept |
| `Domain\Contract\SettingMigration`, `SettingMigrationStore` | A migration of the stored values, and what it reads and writes |
| `Domain\Migration\Step`, `StepKind`, `StoredValue` | The steps of a migration — rename, convert, default changed, remove — and a stored value as it reads and writes it |
| `Domain\Attribute\AsSetting`, `AsSettingSection`, `AsSettingMigration` | The declarations by attribute |
| `Domain\Exception\SettingsException`, `InvalidSetting`, `InvalidSettingValue`, `UnknownSetting` | The bundle's exceptions |

## Application

| Class | Role |
|---|---|
| `Settings` | Reading typed with the default, previewing, writing, restoring, forgetting; reset between requests |
| `SettingPreview` | A setting before a save: its value now, the proposed one, whether they differ |
| `Migration\SettingMigrator`, `Migration\MigrationReport` | The migrations applied once, in their order, or tried on a dry run, and what each changed |
| `SettingCatalog` | The declarations, in their order |
| `DocumentAttributes` | The attributes of the page's root element |
| `BuiltInSettings` | The bundle's sections, display preferences and notification preferences |
| `Visitor\PreferenceCookieFormat` | The cookie of a visitor's preferences, read and written |
| `Event\SettingsChanged` | A change, by identifiers |
| `Contract\CurrentUser`, `Contract\VisitorPreferences` | The ports: see [Extension points](extension-points.md) |

## Infrastructure

| Class | Role |
|---|---|
| `Doctrine\DbalSettingRepository`, `SettingTables`, `SettingSchemaListener` | DBAL storage |
| `Memory\InMemorySettingRepository` | Storage in memory, for tests |
| `Security\SecurityCurrentUser` | The current user from Symfony Security |
| `Http\SettingsController`, `Http\PreferenceCookie`, `Http\Routes` | The pages and the cookie |
| `Form\SettingsForm` | The form of a section |
| `Twig\SettingsExtension` | `phil_setting()` and `phil_document_attributes()` |
| `Notifications\SettingsNotificationPreferences` | The notifications bundle's preferences |
| `Privacy\SettingsPrivacy` | The privacy bundle's export and erasure |
| `Console\MigrateSettingsCommand` | `phil:settings:migrate`, with `--dry-run` |
| `DependencyInjection\SettingsServices`, `DeclaredSettingsPass`, `SettingMigrationsPass` | The services, the declarations by attribute, and the migrations by version and order |

## Files

| File | Role |
|---|---|
| `config/routes.php`, `config/routes/site.php` | The preferences and the site's settings |
| `templates/settings.html.twig`, `templates/_settings.html.twig` | The page and its part |
| `translations/phil_settings.en.php`, `phil_settings.fr.php` | The texts, in English and French |
| `assets/src/document_controller.js` | The Stimulus controller of the display preferences |
