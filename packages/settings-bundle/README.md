# phil/settings-bundle

**Settings people change themselves, for Symfony.**

phil/settings-bundle keeps the settings each user changes — their preferences — and those of the site, which the users
of a role change. Packages and the application declare them, by configuration or attribute; the bundle reads them
typed with their default, stores what differs from it one row per person and key, and generates a page of forms
section by section. Its display preferences — colour scheme, motion, contrast, text size — are kept for visitors too, in
a strictly necessary cookie, and written on the page's root element, where the stylesheet applies them as it applies
the system's media queries.

## Status

| Part | State |
|---|---|
| Declarations by configuration and attribute, ordered by `before` and `after` | Implemented and tested |
| Typed reading with the default, saving, restoring, forgetting, change events | Implemented and tested |
| DBAL storage, one read per subject and request, reset between requests | Implemented and tested |
| The preferences and the site's settings, forms by section, CSRF, English and French | Implemented and tested |
| Display preferences for users and visitors, root attributes, Stimulus controller | Implemented and tested |
| The notifications bundle's preferences, the privacy bundle's export and erasure | Implemented and tested |
| Migrations of the stored values — rename, convert, default changed, remove —, versioned, by a command | Implemented and tested |
| The preview of a section before saving | Implemented and tested |
| The site's settings behind SecurityBundle, in the functional suite | Implemented and tested |
| The Flex recipe: the bundle, its configuration and its routes | Written in `recipe/0.1`, for the recipes repository of PHIL |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the recipe, the tables, the routes and the root element. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Settings](doc/settings.md) | Declaring, ordering, reading, writing, and the change events. |
| [The settings pages](doc/pages.md) | The preferences and the site's settings, their forms, preview and template. |
| [Display preferences](doc/display.md) | Theme, motion, contrast and text size, on the page and for visitors. |
| [Notifications and privacy](doc/integrations.md) | The notification preferences, and the export and erasure of a person's settings. |
| [Migrations](doc/migrations.md) | Renames, type changes and defaults of a declaration that evolved, applied once by a command. |
| [Storage](doc/storage.md) | The table, the reads of a request, and the worker reset. |
| [Extension points](doc/extension-points.md) | The ports, the declarations, the services and the events. |
| [Testing](doc/testing.md) | The bundle's tests, its benchmarks and its fuzz target. |
| [Reference](doc/reference.md) | Every class and file of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\SettingsBundle\PhilSettingsBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_settings.yaml
phil_settings:
    sections:
        author: { before: [notifications] }
    settings:
        author.signature: { type: text, section: author, max_length: 120 }
        site.registrations_open: { type: boolean, default: true, scope: site }
```

```twig
<html lang="{{ app.request.locale }}" {{ phil_document_attributes() }}>
```

```php
$settings->string('author.signature');        // the current user's, or the default
$settings->save(Subject::user($id), ['author.signature' => 'Ada']);
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later with FrameworkBundle and TwigBundle, and Doctrine DBAL.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
