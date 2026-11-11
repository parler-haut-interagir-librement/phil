# phil/instant-messenger-bundle

**A messaging handle field with its service, a validation constraint and Twig filters for Symfony.**

`phil/instant-messenger-bundle` brings `phil/instant-messenger` into a Symfony application. `HandleType` is two
fields that give a `Handle`: the service, chosen among the accepted ones, and the handle as the person types it. The
constraint `HandleOfType` checks a `Handle` by its service, and a text by detecting its service among the accepted
ones. Twig gets `messenger_display`, `messenger_link` and `messenger_label`. Messages come in English and French.

It is for Symfony applications whose forms collect a way to reach someone: a member's Signal, a shop's WhatsApp, an
organiser's Matrix ID. The field asks for the service because handles collide: a user name or a phone number fits
several services. The accepted services are named as `phil/url-bundle` names kinds: `null` for every international
service, an enum class, a case, an `EnumSet` or a list of these.

Use `phil/instant-messenger` directly outside Symfony or inside a domain model. The bundle has no column type: a
handle is stored as two columns the application maps, the service with its own enum. It wires the library into
Symfony Form, Validator and Twig, and adds no rule of its own.

## Status

| Part | State |
|---|---|
| `HandleType`: the service chosen among the accepted ones, and the handle | Implemented and tested |
| `HandleOfType`: a handle, or a text detected, on an accepted service | Implemented and tested |
| Twig: `messenger_display`, `messenger_link`, `messenger_label` | Implemented and tested |
| Messages in English and French | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, registering. |
| [Forms and validation](doc/forms-and-validation.md) | `HandleType`, `HandleOfType`, the accepted services, and storing a handle. |
| [Twig](doc/twig.md) | The three filters. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/instant-messenger-bundle
```

```php
$builder->add('messenger', HandleType::class, ['types' => [KnownMessenger::Signal, KnownMessenger::WhatsApp]]);
```

```twig
{% set link = shop.messenger|messenger_link %}
{% if link %}<a href="{{ link }}">{{ shop.messenger|messenger_label }} {{ shop.messenger|messenger_display }}</a>{% else %}{{ shop.messenger|messenger_label }} {{ shop.messenger|messenger_display }}{% endif %}
```

## Requirements

PHP 8.5 or later; `phil/instant-messenger` 0.1 and `phil/enums` 0.3; `symfony/config`,
`symfony/dependency-injection`, `symfony/form`, `symfony/options-resolver` and `symfony/validator` 8.2 or later.
Twig is optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
