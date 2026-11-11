# phil/phone-bundle

**A phone number field, a validation constraint, a column type and Twig filters for Symfony.**

`phil/phone-bundle` brings `phil/phone` into a Symfony application. `PhoneType` is a `tel` field that reads a number
typed without its country code in a default country, the bundle's `default_region` unless the field names its own.
It refuses a number in no numbering plan, and shows a number of its country nationally and any other
internationally. The constraint `ValidPhoneNumber` accepts a valid number of the types and countries it names, all of
them by default. With DoctrineBundle, the column type `phil_phone` stores a number in E.164 with its extension, and
Twig gets the filters `phone` and `phone_link`. Messages come in English and French.

It is for Symfony applications whose forms collect phone numbers: a member's mobile, a shop's line, a contact at an
organisation. The default country lets people type numbers as they write them at home. The constraint lets a form ask
for a mobile only, or for a number from Canada or the United States only.

Use `phil/phone` directly outside Symfony or inside a domain model. The bundle holds no numbering rule: parsing,
validation and formatting are the library's, and the bundle wires them into Symfony Form, Validator, Doctrine DBAL
and Twig.

## Status

| Part | State |
|---|---|
| `PhoneType`: a number typed with or without its country code, refused when in no plan | Implemented and tested |
| `ValidPhoneNumber`: a valid number, of accepted types and countries | Implemented and tested |
| The column type `phil_phone` | Implemented and tested |
| Twig: `phone`, `phone_link` | Implemented and tested |
| Messages in English and French | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, registering, `default_region`. |
| [Forms and validation](doc/forms-and-validation.md) | `PhoneType` and `ValidPhoneNumber`. |
| [Doctrine](doc/doctrine.md) | The column type `phil_phone`. |
| [Twig](doc/twig.md) | The two filters. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/phone-bundle
```

```yaml
# config/packages/phil_phone.yaml
phil_phone:
    default_region: CA
```

```php
$builder->add('phone', PhoneType::class);
```

```twig
{% set link = shop.phone|phone_link %}
{% if link %}<a href="{{ link }}">{{ shop.phone|phone('national') }}</a>{% endif %}
```

## Requirements

PHP 8.5 or later; `phil/phone` 0.1; `symfony/config`, `symfony/dependency-injection`, `symfony/form`,
`symfony/options-resolver` and `symfony/validator` 8.2 or later. Doctrine DBAL and Twig are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
