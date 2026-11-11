# phil/url-bundle

**Link fields that detect their kind, a validation constraint, a column type and Twig filters.**

`phil/url-bundle` brings `phil/url` into a Symfony application. `TypedUrlType` is one text field that reads an
address, `https` by default, and detects its kind among the enum sets it accepts, giving a `TypedUrl`. The constraint
`UrlOfType` checks that a text, a `Url` or a `TypedUrl` is of an accepted kind. With DoctrineBundle, the column type
`phil_url` keeps a normalised `Url` in a `VARCHAR(2048)`. Twig gets `url_display`, `url_type()` and `url_type_label`.
Messages come in English and French.

It is for Symfony applications whose forms collect links: a social profile, a shop's website, an organisation's page
in a registry. A field or a constraint accepts the international kinds of `KnownUrlType`, the application's own
enums, or both, named as an enum class, a case, an `EnumSet` or a list of these. An address and its kind are stored
as two columns the application maps itself, the kind with its own enum.

Use `phil/url` directly outside Symfony or inside a domain model. The bundle adds no rule of its own: normalisation,
kinds and detection come from the library, and the bundle wires them into Symfony Form, Validator, Doctrine DBAL and
Twig.

## Status

| Part | State |
|---|---|
| `TypedUrlType`: one text field, the kind detected | Implemented and tested |
| `UrlOfType`: a text, a `Url` or a `TypedUrl` of an accepted kind | Implemented and tested |
| The column type `phil_url`, optional | Implemented and tested |
| Twig: `url_display`, `url_type()`, `url_type_label` | Implemented and tested |
| Messages in English and French | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, registering. |
| [Accepted kinds](doc/kinds.md) | How a field or a constraint names the kinds it accepts, the application's own included. |
| [Forms](doc/forms.md) | `TypedUrlType`. |
| [Validation](doc/validation.md) | `UrlOfType`. |
| [Doctrine](doc/doctrine.md) | The column type `phil_url`, and keeping an address with its kind. |
| [Twig](doc/twig.md) | `url_display`, `url_type()`, `url_type_label`. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/url-bundle
```

```php
$builder->add('instagram', TypedUrlType::class, ['types' => KnownUrlType::Instagram, 'required' => false]);

#[UrlOfType([KnownUrlType::Facebook, KnownUrlType::Instagram])]
public ?string $social = null;
```

## Requirements

PHP 8.5 or later; `phil/url` 0.1 and `phil/enums` 0.3; `symfony/config`, `symfony/dependency-injection`,
`symfony/form`, `symfony/options-resolver` and `symfony/validator` 8.2 or later. Doctrine DBAL and Twig are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
