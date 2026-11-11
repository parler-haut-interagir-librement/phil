# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-intl` | any | The request's language, `Locale` |
| `phil/address` | ^0.1@dev | The addresses, formats and subdivisions |
| `symfony/form`, `symfony/options-resolver`, `symfony/intl` | ^8.2 | The form and its country field |
| `symfony/validator` | ^8.2 | `ValidAddress` |
| `symfony/http-foundation` | ^8.2 | The format endpoint |
| `symfony/translation-contracts` | ^3.6 | Labels and messages translated, when a translator is there |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration |
| `doctrine/dbal`, `symfony/routing`, `twig/twig` | optional | The column type, the endpoint's route, the filters |

```sh
composer require phil/address-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\AddressBundle\PhilAddressBundle::class => ['all' => true],
];
```

## Configuration

```yaml
phil_address:
    default_country: CA                         # the country of a new address; null asks for it
    format_url: /address-format/__COUNTRY__     # the endpoint the Stimulus controller reads; null leaves it out
```

```yaml
# config/routes/phil_address.yaml
phil_address:
    resource: '@PhilAddressBundle/config/routes.php'
    prefix: /address-format
```

With Symfony UX, the Stimulus controller is in `assets/`, declared in its `package.json`.
