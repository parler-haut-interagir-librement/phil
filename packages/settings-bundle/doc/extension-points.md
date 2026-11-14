# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Ports written by the application

### `CurrentUser`

`Phil\SettingsBundle\Application\Contract\CurrentUser` (`current_user`) — who the current request is for, by the
identifier their settings are stored under. Symfony Security's user identifier by default.

| Method | Contract |
|---|---|
| `id(): ?string` | The user's identifier, at most 190 bytes without space or control character; `null` for a visitor |

### `VisitorPreferences`

`Phil\SettingsBundle\Application\Contract\VisitorPreferences` — the values a visitor keeps, the preference cookie by
default. Alias it to keep them elsewhere; the bundle checks each value again before it uses it.

| Method | Contract |
|---|---|
| `values(): array<string, bool\|string>` | The visitor's values of the settings kept for visitors, by key |

### `SettingRepository`

`Phil\SettingsBundle\Domain\Contract\SettingRepository` — where the values are kept, DBAL by default. An implementation
keeps only what it is given, encoded, and answers a subject's values in one read.

| Method | Contract |
|---|---|
| `values(Subject $subject): array<string, string>` | The encoded values, by key |
| `save(Subject $subject, array $values, array $removed, DateTimeImmutable $at): void` | Writes and removes at once |
| `erase(Subject $subject): list<string>` | Removes every value, and gives the keys removed, sorted |

### `SettingMigrationStore`

`Phil\SettingsBundle\Domain\Contract\SettingMigrationStore` — what the migrations read and write across subjects,
implemented by the DBAL and memory stores. A store of the application's implements it beside `SettingRepository`, and
aliases it.

| Method | Contract |
|---|---|
| `appliedMigrations(): list<string>` | The versions applied |
| `valuesOf(string $key): list<StoredValue>` | Every stored value of the key, whatever its subject |
| `applyMigration(string $version, list<StoredValue> $changes, DateTimeImmutable $at): void` | Writes the values, removes those whose value is null, and records the version, at once; a version already recorded fails |

### `SettingMigration`

`Phil\SettingsBundle\Domain\Contract\SettingMigration` — a versioned change of the stored values: see
[Migrations](migrations.md).

| Method | Contract |
|---|---|
| `steps(): list<Step>` | The steps, in the order they run |

## Declarations

| To | Do |
|---|---|
| Declare a setting or a section in the application | `phil_settings.settings` and `phil_settings.sections`, or `#[AsSetting]` and `#[AsSettingSection]` on an autoconfigured class |
| Declare them in a package | Prepend the configuration of `phil_settings` |
| Place a section or a setting among others | `before` and `after`, never a number |
| Write a choice on the page's root element | `html_attribute` |
| Keep a boolean or a choice for visitors | `anonymous` |
| Migrate the stored values of a declaration that evolved | `#[AsSettingMigration]` on a `SettingMigration`, or the tag `phil_settings.migration` with `version`, `before` and `after` |

## Services the application uses

| Service | For |
|---|---|
| `Phil\SettingsBundle\Application\Settings` | Reading and writing values |
| `Phil\SettingsBundle\Application\SettingCatalog` | The sections and settings declared, in their order |
| `Phil\SettingsBundle\Application\DocumentAttributes` | The attributes of the root element |
| `Phil\SettingsBundle\Application\Migration\SettingMigrator` | The migrations pending, applied or tried on a dry run |
| `Phil\SettingsBundle\Infrastructure\Notifications\SettingsNotificationPreferences` | The notifications bundle's preferences |

## Events

`Phil\SettingsBundle\Application\Event\SettingsChanged`, dispatched on the event dispatcher after each change or
erasure, with identifiers only: see [Settings](settings.md#events).

## Templates and translations

| To | Do |
|---|---|
| Show the pages in the application's layout | `template`, including `@PhilSettings/_settings.html.twig` |
| Change a text | Override a key of the `phil_settings` domain, in `translations/phil_settings.<locale>.php` or YAML |
| Translate the application's settings | Their `label`, `help` and `<key>.choices.<choice>` keys, in their `translation_domain` |
