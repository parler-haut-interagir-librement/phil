# phil/content-warnings-bundle

**Content warnings in Symfony applications: a catalogue from configuration, warnings on any entity, and content
folded or hidden as each reader chooses.**

phil/content-warnings-bundle brings `phil/content-warnings` into Symfony. The catalogue of warning themes is built
from the library's base list, in six languages, completed or replaced by the application's configuration and
definition files; a definition that breaks a rule stops the container build. The column type `phil_content_warnings`
keeps the warnings of any entity — a game, a book, an article, an event — in one JSON column. `ContentWarningsType`
picks them among the themes, grouped by category, and `WarningPreferencesType` lets a reader choose a display per
category and per theme. The template `@PhilContentWarnings/fold.html.twig` shows a piece of content with its warnings,
folds it behind them in an accessible details element that the Stimulus controller `reveal` drives, or leaves it out.
Labels and messages are in English, French, Spanish, German, Italian and Portuguese.

It is for Symfony applications that publish content some readers would rather approach with care, and that let those
readers choose. Preferences are read through the port `WarningPreferencesStore`: the bundle keeps them in the session
by default, and an application with member settings implements the port over them.

Outside Symfony, or for the catalogue, warnings and decisions alone, use `phil/content-warnings`. The bundle is a thin
layer over it: configuration, a column type, form fields, Twig functions, a template and a store.

## Status

| Part | State |
|---|---|
| The catalogue from configuration and definition files | Implemented and tested |
| The column type `phil_content_warnings` | Implemented and tested |
| `ContentWarningsType`, its form theme, and `WarningPreferencesType` | Implemented and tested |
| Twig functions and `@PhilContentWarnings/fold.html.twig` | Implemented and tested |
| The Stimulus controller `reveal` and its stylesheet | Implemented and tested |
| `WarningPreferencesStore`, with the session and in-memory stores | Implemented and tested |
| Labels and messages in six languages | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and registering. |
| [Configuration](doc/configuration.md) | The base list, definition files, the default display, locales and the store. |
| [Doctrine](doc/doctrine.md) | The column type `phil_content_warnings`. |
| [Forms](doc/forms.md) | `ContentWarningsType`, its theme, and `WarningPreferencesType`. |
| [Display](doc/display.md) | The template, the Twig functions, the Stimulus controller, the stylesheet and accessibility. |
| [Preferences](doc/preferences.md) | The port `WarningPreferencesStore` and the stores shipped. |
| [Extension points](doc/extension-points.md) | What an application configures, implements, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/content-warnings-bundle
```

```php
#[ORM\Column(type: 'phil_content_warnings', nullable: true)]
private ?ContentWarnings $warnings = null;

$builder->add('warnings', ContentWarningsType::class);
```

```twig
{% embed '@PhilContentWarnings/fold.html.twig' with {warnings: book.warnings} %}
    {% block content %}{{ book.excerpt }}{% endblock %}
{% endembed %}
```

## Requirements

PHP 8.5 or later with `ext-intl`; `phil/content-warnings` 0.1; `symfony/config`, `symfony/dependency-injection`,
`symfony/form` and `symfony/options-resolver` 8.2 or later, `symfony/service-contracts` 3.6 or later. Doctrine DBAL,
HttpFoundation and Twig are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
