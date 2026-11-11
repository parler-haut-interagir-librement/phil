# phil/address-bundle

**An address form for Symfony whose fields follow the country the person picks.**

phil/address-bundle brings the addresses of `phil/address` into Symfony. `AddressType` shows the fields of the chosen
country's format, in its order, named as it names them and required as it requires them. Where the country has states
or provinces, the administrative area is a choice of them. When the country changes, a Stimulus controller reads the
bundle's format endpoint and shows, hides and relabels the fields in the browser. On submission, the address is
checked against its country and each wrong field gets its own error. The bundle also gives the constraint
`ValidAddress`, the Doctrine column type `phil_address` for one JSON column, the Twig filters `address`,
`address_lines` and `address_label`, and the Twig functions `country_name` and `address_schema`. Labels and messages
are in English and French.

It is for Symfony applications where a customer, a member or an editor types an address that may be in any country,
and where pages or postal labels show it back. Options limit the countries offered and add the recipient's names.

Outside Symfony, use `phil/address` on its own. The bundle keeps the rules in the library. The form and the
constraint ask its validator, the endpoint reads its formats, the column type uses `AddressArray`, and Twig its
formatter.

## Status

| Part | State |
|---|---|
| `AddressType`: fields of the country, states as choices, errors per field | Implemented and tested |
| The format endpoint and the Stimulus controller `address` | Implemented and tested |
| `ValidAddress`, the column type `phil_address` | Implemented and tested |
| Twig: `address`, `address_lines`, `address_label`, `country_name`, `address_schema` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, configuration, the route. |
| [Forms](doc/forms.md) | `AddressType`, its options, and its Stimulus controller. |
| [Validation and storage](doc/validation-and-storage.md) | `ValidAddress` and `phil_address`. |
| [Twig](doc/twig.md) | The filters and the functions. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/address-bundle
```

```yaml
phil_address:
    default_country: CA
    format_url: /address-format/__COUNTRY__
```

```php
$builder->add('address', AddressType::class);
```

```twig
{{ shop.address|address|nl2br }}
```

## Requirements

PHP 8.5 or later with `ext-intl`; `phil/address` 0.1; Symfony 8.2's config, dependency injection, form, HTTP foundation, Intl,
options resolver and validator, and the translation contracts. Doctrine DBAL, the router and Twig are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
